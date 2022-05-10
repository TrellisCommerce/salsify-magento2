<?php

namespace Trellis\Salsify\Model\Sync;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Util\Exception;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Helper\Product;
use Trellis\Salsify\Model\MediaDownloadManager;
use Trellis\Salsify\Model\Product\Gallery\Video\Processor;
use Trellis\Salsify\Model\Sync;

class ProductFeed extends Sync
{

    /**
     * Default SKU key for product feed
     */
    const DEFAULT_SKU_KEY = 'sku';

    /* PUBLIC METHODS */
    public function updateViaManual(){
        // todo implement updateViaManual()
        // 20210802 travish we'll just go through the backdoor for now
        $manual = true;
        $this->updateViaApi($manual);
    }

    public function updateViaApi($manual = false)
    {
        try {
            // Get the JSON for the products list from the Salsify API:
            $salsifyProducts = $this->_client->getProductFeed();

            $this->_notifySyncStarted($salsifyProducts, "Starting Salsify sync from product feed via manual button");

            $this->_createSalsifyAttributes();

            $mappedSalsifyRecords = [];
            foreach ($salsifyProducts as $salsifyProduct) {
                $salsifyProduct = $this->_replaceSalsifyAttributeKeysWithMagentoAttributeCodes($salsifyProduct);

                $mappedSalsifyRecords[] = $this->_mapSpecialAttributeTypes($salsifyProduct);
            }

            $salsifyProductsById = $this->_getSalsifyProductsById($mappedSalsifyRecords);

            // Update existing products, and create non-existent ones:
            $this->upsertProducts($salsifyProductsById);

            // Optionally delete Salsify-sync'd records that are no longer in the Salsify response:
            $this->deleteProducts(array_map(function ($salsifyProduct) {
                return $salsifyProduct[ self::SALSIFY_ID_KEY ];
            }, $salsifyProducts), true);
        } catch (\Exception $e) {
            $this->_notifySyncError($e);
            throw $e;
        }

        $this->_notifySyncFinished();
    }

    /**
     * @param $magentoProduct
     * @param $salsifyRecord
     *
     * @return mixed
     */
    public function updateDownloadableSamples(&$magentoProduct, $salsifyRecord)
    {
        $sampleFieldName = $this->_data->getDownloadableSampleField();
        if (array_key_exists($sampleFieldName, $salsifyRecord)) {
            //todo sample downloads
        }

        return $magentoProduct;
    }

    /**
     * @param $salsifyAttributes
     * @param $salsifyProductsById
     *
     * @throws \Exception
     */
    public function upsertProducts($salsifyProductsById)
    {
        $this->_logger->info("--- STARTING UPSERT ---");
        $this->_syncExecution->start();
        $this->_logger->info("--- UPSERT STARTED: " . $this->_syncExecution->getStartTime());

        $this->_logger->info("--- MAGENTO EDITION: " . $this->_data->getMagentoEdition());
        $this->_logger->info("--- MAGENTO VERSION: " . $this->_data->getMagentoVersion());
        $this->_logger->info("--- SALSIFY CONNECTOR VERSION: " . $this->_data->getExtensionVersion());

        $this->_logger->info("--- SALSIFY CONNECTOR CONFIGURATION --- ");
        foreach ($this->_feedConfig->getEnabledConfiguration() as $config) {
            $this->_logger->info($config);
        }

        $this->_updatedProducts = $this->_updateProducts($salsifyProductsById);
        $this->_createProducts($salsifyProductsById, $this->_updatedProducts);
        $this->_updateConfigurables($salsifyProductsById);

        $this->_updateProductRelations($salsifyProductsById);

        $this->_syncExecution->end();
        $time = $this->_syncExecution->getFullStats();
        $this->_logger->info("--- UPSERT FINISHED: " . $time['end_time']);
        $this->_logger->info("--- TOTAL EXECUTION TIME: " . $time['execution_time'] . " seconds.");
        if ($this->_data->getDefaultClearDebugLog()) {
            // clear the debug log after successful syncs, if enabled
            $this->_logger->info("--- CLEARING LOG FILE ---");
            $this->_debug->execute();
        }
        $this->_logger->info("--- FINISHED UPSERT ---");
    }


    /* PROTECTED METHODS */

    /**
     * @return array
     */
    protected function _getFlippedAttributeMapping()
    {
        $mapping = $this->_productFeed->getPropertyMapping();
        $result = [];
        foreach ($mapping as $property => $attribute) {
            if (strpos($property, ',') !== false) {
                // we assume that the property is a concatenation of properties.
                $properties = explode(',', $property);
                $result[$attribute] = $properties;
            } elseif (is_array($attribute)) {
                foreach ($attribute as $magentoAttribute) {
                    $result[$magentoAttribute] = $property;
                }
            } else {
                $result[$attribute] = $property;
            }
        }
        return $result;
    }

    /**
     * Replace Salsify record keys with Magento attribute codes.
     *
     * @param array $salsifyRecord
     *
     * @return array
     */
    protected function _replaceSalsifyAttributeKeysWithMagentoAttributeCodes(array $salsifyRecord)
    {
        $mappedSalsifyRecord = [];

        $mappedSalsifyRecord[ self::TRELLIS_SALSIFY_ID_ATTRIBUTE ] = $salsifyRecord[ self::SALSIFY_ID_KEY ];
        unset($salsifyRecord[ self::SALSIFY_ID_KEY ]);
        $mappedSalsifyRecord[ self::TRELLIS_SALSIFY_LAST_UPDATED ] = $salsifyRecord[ self::SALSIFY_LAST_UPDATED ];
        unset($salsifyRecord[ self::SALSIFY_LAST_UPDATED ]);

        // Carry over Salsify parent id from $salsifyRecord
        if (isset($salsifyRecord[ self::SALSIFY_PARENT_KEY ])) {
            $mappedSalsifyRecord[ self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE ] = $salsifyRecord[ self::SALSIFY_PARENT_KEY ];
            unset($salsifyRecord[ self::SALSIFY_PARENT_KEY ]);
        }

        // Unset
        foreach ($salsifyRecord as $property => $value) {
            if (strpos($property, self::SALSIFY_PROPERTY_PREFIX) === 0) {
                unset($salsifyRecord[ $property ]);
            }
        }
        unset($salsifyRecord['Identifier']);

        // Handle any custom mapping we've defined in the admin
        $flippedMapping = $this->_getFlippedAttributeMapping();
        $concatenatedProperties = [];
        // We need to know whether our $flippedMapping array contains duplicate values, so we don't unset them later.
        // first, check whether we've concatenated any salsify properties.
        // we'll know we have if we have array values in $flippedMapping.
        foreach ($flippedMapping as $key => $value) {
            if (is_array($value)) {
                $concatenatedProperties[$key] = $value;
                // remove array values for now, we'll add them back later.
                // we need a flat array for array_count_values
                unset($flippedMapping[$key]);
            }
        }
        $flippedMappingValues = array_count_values($flippedMapping);
        if (!empty($concatenatedProperties)) {
            foreach ($concatenatedProperties as $singleMagentoAttribute => $multipleSalsifyProperties) {
                foreach ($multipleSalsifyProperties as $concatenatedSalsifyProperty) {
                    // check if the property already exists in $flippedMappingValues
                    if (array_key_exists($concatenatedSalsifyProperty, $flippedMappingValues)) {
                        // add 1
                        $flippedMappingValues[$concatenatedSalsifyProperty]++;
                    } else {
                        // add the property
                        $flippedMappingValues[$concatenatedSalsifyProperty] = 1;
                    }
                }
                // add our previously unset attribute array back
                $flippedMapping[$singleMagentoAttribute] = $multipleSalsifyProperties;
            }
        }
        foreach ($flippedMapping as $magentoAttribute => $salsifyAttribute) {
            if (is_array($salsifyAttribute)) {
                foreach ($salsifyAttribute as $salsifyProperty) {
                    if (isset($salsifyRecord[$salsifyProperty])) {
                        $mappedSalsifyRecord[$magentoAttribute][$salsifyProperty] = $salsifyRecord[$salsifyProperty];
                        // We may still need $salsifyAttribute if a product feed property is mapped to more than one attribute,
                        // or a property is concatenated.
                        if (array_key_exists($salsifyProperty, $flippedMappingValues)
                            && $flippedMappingValues[$salsifyProperty] > 1) {
                            $flippedMappingValues[$salsifyProperty]--;
                            continue;
                        }
                        unset($salsifyRecord[$salsifyProperty]);
                    }
                }
            } elseif (isset($salsifyRecord[ $salsifyAttribute ])) {
                $mappedSalsifyRecord[ $magentoAttribute ] = $salsifyRecord[ $salsifyAttribute ];
                // We may still need $salsifyAttribute if a product feed property is mapped to more than one attribute,
                // or a property is concatenated.
                if (array_key_exists($salsifyAttribute, $flippedMappingValues)
                    && $flippedMappingValues[$salsifyAttribute] > 1) {
                    $flippedMappingValues[$salsifyAttribute]--;
                    continue;
                }
                unset($salsifyRecord[ $salsifyAttribute ]);
            }
        }

        // Merge all other properties from Salsify, assuming it maps nicely to Magento attributes
        $mappedSalsifyRecord = array_merge($mappedSalsifyRecord, array_diff_key($salsifyRecord, $mappedSalsifyRecord));

        if (isset($salsifyRecord[ self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE ])) {
            $mappedSalsifyRecord[ self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE ] = $salsifyRecord[ self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE ];
        }

        return $mappedSalsifyRecord;
    }

    /**
     * @param $magentoProduct
     * @param $salsifyRecord
     * @param $detailsField
     *
     * @return mixed
     */
    protected function _updateDownloadableOptions(&$magentoProduct, $salsifyRecord, $detailsField)
    {
        $this->_logger->info("detailsField: " . json_encode($detailsField));
        if (array_key_exists($detailsField, $salsifyRecord)) {
            $this->_logger->info('DOWNLOADABLE OPTION LIFTOFF!');
            $this->_logger->info('detailsField Value: ' . $salsifyRecord[ $detailsField ]);

            $linkDetails = $salsifyRecord[ $detailsField ];
            if ($this->_isJsonValid($linkDetails)) {
                $this->_logger->info('VALID JSON!');
                $links       = [];
                $linkDetails = json_decode($linkDetails, true);
                if (!is_array($linkDetails)) {
                    $this->_logger->info('linkDetails IS NOT AN ARRAY!');
                }
                foreach ($linkDetails as $linkDetail => $value) {
                    $this->_logger->info($linkDetail . ":" . $value);
                }
                $linkData['title'] = $linkDetails['title'];
                switch ($linkDetails['type']) {
                    case 'file':
                        $linkData['type']      = \Magento\Downloadable\Helper\Download::LINK_TYPE_FILE;
                        $linkData['link_url']  = null;
                        $linkData['is_delete'] = 0;
                        break;
                    case 'url':
                        $linkData['type']      = \Magento\Downloadable\Helper\Download::LINK_TYPE_URL;
                        $linkData['link_url']  = $linkDetails['link_url'];
                        $linkData['is_delete'] = null;
                        break;
                }
                if (array_key_exists('is_sharable', $linkDetails)) {
                    switch ($linkDetails['is_sharable']) {
                        case 'yes':
                            $linkData['is_sharable'] = \Magento\Downloadable\Model\Link::LINK_SHAREABLE_YES;
                            break;
                        case 'no':
                            $linkData['is_sharable'] = \Magento\Downloadable\Model\Link::LINK_SHAREABLE_NO;
                            break;
                        default:
                            $linkData['is_sharable'] = \Magento\Downloadable\Model\Link::LINK_SHAREABLE_CONFIG;
                            break;
                    }
                }
                $link = $this->_fileLinkInterfaceFactory->create(['data' => $linkData]);
                $link->setId(null);
                $link->setTitle($linkData['title']);
                $link->setLinkType($linkData['type']);
                $link->setStoreId($magentoProduct->getStoreId());
                $link->setWebsiteId($magentoProduct->getStore()->getWebsiteId());
                $link->setProductWebsiteIds($magentoProduct->getWebsiteIds());
                $link->setSortOrder(1);
                $link->setPrice(0);
                $link->setNumberOfDownloads(0);
                $links[]   = $link;
                $extension = $magentoProduct->getExtensionAttributes();
                $extension->setDownloadableProductLinks($links);
                $magentoProduct->setExtensionAttributes($extension);
            }
            $this->updateDownloadableSamples($magentoProduct, $salsifyRecord);
        }

        return $magentoProduct;
    }

    /**
     * @param $string
     *
     * @return bool
     */
    protected function _isJsonValid($string)
    {
        json_decode($string);

        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $categoryHierarchy
     * @param $magentoProduct
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _createAndAssignCategories($categoryHierarchy, &$magentoProduct)
    {
        $sku            = $magentoProduct->getSku();
        $category       = null;
        $categoryIds    = [];
        $customRootNode = $this->_data->getRootNodeId() != 1;
        $level          = 1;
        if ($customRootNode) {
            $rootNode = $this->_categoryCollectionFactory->create()
                ->addFieldToFilter('entity_id', $this->_data->getRootNodeId())
                ->getFirstItem();

            $category = $rootNode;
        }
        foreach ($categoryHierarchy as $categoryName) {
            $categoryName = trim($categoryName);
            $parentId     = null;
            $parentPath   = null;
            if ($category == null) {
                $this->_logger->info("Creating category " . $categoryName);
                $categoryCollection = $this->_categoryCollectionFactory->create();
                $categoryCollection->addLevelFilter($level);
                if ($customRootNode) {
                    $categoryCollection->addFieldToFilter('parent_id', $this->_data->getRootNodeId());
                }
                $categoryCollection->addAttributeToFilter('name', $categoryName);
                $category = $categoryCollection->getFirstItem();
            } else {
                $level              = $category->getLevel() + 1;
                $categoryCollection = $this->_categoryCollectionFactory->create()
                    ->addAttributeToFilter('name', $categoryName)
                    ->addPathsFilter($category->getPath() . '/')
                    ->addLevelFilter($level);
                $parentId           = $category->getId();
                $parentPath         = $category->getPath();
                $category           = $categoryCollection->getFirstItem();
            }
            if ($category->getName() != $categoryName) {
                $category       = $this->_categoryFactory->create();
                $rootCategoryId = $this->_data->getRootNodeId();
                $category->setParentId($parentId != null? $parentId : $rootCategoryId);
                $category->setLevel($level);
                $category->setIsActive(true);
                $category->setPath($parentPath != null? $parentPath : ($rootCategoryId));
                $category->setName($categoryName);
                try {
                    $category->save();
                } catch (\Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException $e) {
                    // Set the URL to something with the time attached to guarantee uniqueness:
                    $url      = $category->getName() . time();
                    $cleanUrl = trim(preg_replace('/ +/', '', preg_replace('/[^A-Za-z0-9 ]/', '', urldecode(html_entity_decode(strip_tags($url))))));
                    $category->setUrlKey($cleanUrl);
                    $category->save();
                }
            }
            $categoryIds[] = $category->getId();
        }
        $this->_categoryLinkManagement->assignProductToCategories($sku, $categoryIds);

        return $magentoProduct;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param array                          $salsifyRecord
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _updateProductMediaGallery(\Magento\Catalog\Model\Product $product, $salsifyRecord)
    {
        if ($this->_data->getVideoEnabled()) {
            $this->processVideoGalleryEntries($product, $salsifyRecord);
        }

        // If media gallery not enabled, return
        if (!$this->_data->getMediaGalleryEnabled()) {
            return;
        }

        // Pull out SKU:
        $sku = $product->getSku();
        $this->_logger->info("Checking product media for SKU: {$sku}");

        try {
            // Get the JSON for the digital assets list from the Salsify API:
            $salsifyDigitalAssets = $this->_client->getDigitalAssetsFeed();
        } catch (IntegrationException $e) {
            $this->_logger->info("No digital asset field available: " . $e->getMessage());
        }

        if (!is_null($salsifyDigitalAssets)) {
            // Process media gallery:
            $mediaGalleryProperty = $this->_data->getMediaGalleryProperty();
            if (array_key_exists($mediaGalleryProperty, $salsifyRecord)) {
                $this->_logger->info("Updating product media gallery for SKU: {$sku}");
                $digitalAssetIds          = $salsifyRecord[ $mediaGalleryProperty ];
                $filteredDigitalAssetFeed = array_reduce($salsifyDigitalAssets, function ($accumulator, $digitalAsset) use ($digitalAssetIds) {
                    // we need to know if $digitalAssetIds were converted from an array
                    if (strstr($digitalAssetIds, PHP_EOL)) {
                        // if $digitalAssetIds were converted from an array, convert it back.
                        $digitalAssetIds = explode(PHP_EOL, trim($digitalAssetIds));
                        foreach ($digitalAssetIds as $key => $digitalAssetId) {
                            $digitalAssetIds[$key] = trim(strstr($digitalAssetId, ' '));
                        }
                    }
                    if (is_array($digitalAssetIds)) {
                        if (in_array($digitalAsset[ self::SALSIFY_ID_KEY ], $digitalAssetIds)) {
                            $accumulator[] = $digitalAsset;
                        }
                    } else {
                        if ($digitalAsset[ self::SALSIFY_ID_KEY ] == $digitalAssetIds) {
                            $accumulator[] = $digitalAsset;
                        }
                    }
                    return $accumulator;
                }, []);
                $this->_processMediaGalleryEntries($product, $filteredDigitalAssetFeed, [], 'media gallery');
            } else {
                $this->_logger->info("Salsify Media Gallery Property {$mediaGalleryProperty} does not exist for SKU: {$sku}");
            }

            // Handle one-off image mapping:
            if ($this->_data->getImageMappingEnabled()) {
                $imageMapping = $this->_data->getImageMapping();
                foreach ($imageMapping as $salsifyImageField => $tags) {
                    if (array_key_exists($salsifyImageField, $salsifyRecord)) {
                        $filteredDigitalAssetFeed = array_reduce($salsifyDigitalAssets, function ($accumulator, $digitalAsset) use ($salsifyRecord, $salsifyImageField) {
                            $imageFieldExists = array_key_exists($salsifyImageField, $salsifyRecord);
                            if ($imageFieldExists && in_array($digitalAsset[ self::SALSIFY_ID_KEY ], [$salsifyRecord[ $salsifyImageField ]])) {
                                $accumulator[] = $digitalAsset;
                            }
                            return $accumulator;
                        }, []);
                        $this->_processMediaGalleryEntries($product, $filteredDigitalAssetFeed, $tags, 'media role');
                    } else {
                        $this->_logger->info("Image Tag Mapping Property {$salsifyImageField} does not exist for SKU: {$sku}");
                    }
                }
            }
        }
    }

    protected function _processVideoThumbnail($videoUrl)
    {
        $service = $this->_processVideoProvider($videoUrl);
        switch ($service) {
            case 'youtube':
                $api          = $this->_scopeConfig->getValue(self::XML_PATH_YOUTUBE_API);
                $vid          = $this->_processVideoId($videoUrl);
                $url          = 'https://www.googleapis.com/youtube/v3/videos?key=' . $api . '&part=snippet&id=' . $vid;
                $json         = json_decode(file_get_contents($url));
                $thumbnailUrl = $json->items[0]->snippet->thumbnails->default->url;
                $thumbnail    = strrchr($thumbnailUrl, '/');
                $thumbnail    = substr($thumbnail, 1, strlen($thumbnail));
                $extension    = pathinfo($thumbnail, PATHINFO_EXTENSION);
                if (!in_array($extension, ["png", "jpg"])) {
                    $this->_logger->info("Extension not supported: " . json_encode($extension));
                    break;
                }

                return ['filePath' => $this->_downloadManager->downloadImage($thumbnailUrl), 'fileName' => $thumbnail];
                break;
        }

        return 'Under Construction';
    }

    protected function _processVideoId($videoUrl)
    {
        $service = $this->_processVideoProvider($videoUrl);
        switch ($service) {
            case 'youtube':
                preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $videoUrl, $match);

                return $match[1];
                break;
        }

        return 'Under Construction';
    }

    protected function _processVideoProvider($videoUrl)
    {
        $url = parse_url($videoUrl);
        preg_match("/\.([^\/]+)/", $url['host'], $host);

        return strtok(strtolower($host[1]), '.');
    }

    /**
     * Create attributes in Magento from the Salsify attribute feed
     */
    protected function _createSalsifyAttributes()
    {
        if ($this->_data->getCreateAttributesDuringSync()) {
            $this->_logger->info("Starting creating attributes from Salsify.");

            $client            = $this->getClient();
            $salsifyAttributes = $client->getAttributeFeed();

            $this->_productAttributes->createProductAttributes($salsifyAttributes);
        }
    }

    /**
     * Add a notification message when a sync begins.
     *
     * @param array  $salsifyRecords
     * @param string $messageSubject
     */
    protected function _notifySyncStarted(array $salsifyRecords, $messageSubject)
    {
        $skus = array_map(function ($salsifyRecord) {
            $skuKey = self::DEFAULT_SKU_KEY;
            // check to see if the property is mapped.
            // skus aren't necessarily available in product feed syncs, as it is up to the client to map them.
            // 20210802 travish
            if (!array_key_exists($skuKey, $salsifyRecord)) {
                $propertyMapping = $this->_getFlippedAttributeMapping();
                if (!array_key_exists('sku', $propertyMapping)) {
                    throw new Exception(__('Couldn\'t map the SKU property on product feed.'));
                }
                $skuKey = $propertyMapping['sku'];
            }
            //If the mapped sku is not set
            if(!isset($salsifyRecord[$skuKey]))
                return null;
            return $salsifyRecord[$skuKey];
        }, $salsifyRecords);
        $skusString = implode("\n", $skus);

        $count = count($salsifyRecords);
        $notificationMessage = <<<MSG
Syncing a total of {$count} products from url {$this->getClient()->getFeedUrl()}.

Skus:
{$skusString}
MSG;

        $this->_notifierPool->addNotice($messageSubject, $notificationMessage);
    }
}
