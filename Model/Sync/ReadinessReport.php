<?php

namespace Trellis\Salsify\Model\Sync;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Setup\Exception;
use Trellis\Salsify\Model\Sync;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;

class ReadinessReport extends Sync
{

    public function updateViaManual()
    {
        try {
            $client = $this->getClient();

            $salsifyProducts = $client->getReadinessReportFeed();
            $salsifyProductsById = $this->_getSalsifyProductsById($salsifyProducts);

            $this->_notifySyncStarted($salsifyProducts, "Starting Salsify sync from readiness report via manual button");

            $this->_createSalsifyAttributes();

            $this->_updateAttributeSets($salsifyProductsById);

            // Some attributes have special types (i.e. select, multiselect, date) so we have to map those to Magento friendly values
            foreach ($salsifyProductsById as $salsifyProductId => $salsifyProduct) {
                $salsifyProductsById[$salsifyProductId] = $this->_mapSpecialAttributeTypes($salsifyProduct);
            }

            // Update existing products, and create non-existent ones:
            $this->upsertProducts($salsifyProductsById);

            // Optionally delete Salsify-sync'd records that are no longer in the Salsify response:
            $this->deleteProducts(array_map(function ($salsifyProduct) {
                return $salsifyProduct[self::TRELLIS_SALSIFY_ID_ATTRIBUTE];
            }, $salsifyProductsById), true);
        } catch (\Exception $e) {
            $this->_notifySyncError($e);
            throw $e;
        }

        $this->_notifySyncFinished();
    }

    public function updateViaApi()
    {
        try {
            $client = $this->getClient();

            $salsifyProducts = $client->getReadinessReportFeed(null, 'api');
            $salsifyProductsById = $this->_getSalsifyProductsById($salsifyProducts);

            $this->_notifySyncStarted($salsifyProducts, "Starting Salsify sync from readiness report via webhook");

            $this->_createSalsifyAttributes();

            $this->_updateAttributeSets($salsifyProductsById);

            // Some attributes have special types (i.e. select, multiselect, date) so we have to map those to Magento friendly values
            foreach ($salsifyProductsById as $salsifyProductId => $salsifyProduct) {
                $salsifyProductsById[$salsifyProductId] = $this->_mapSpecialAttributeTypes($salsifyProduct);
            }

            // Update existing products, and create non-existent ones:
            $this->upsertProducts($salsifyProductsById);

            // Optionally delete Salsify-sync'd records that are no longer in the Salsify response:
            $this->deleteProducts(array_map(function ($salsifyProduct) {
                return $salsifyProduct[self::TRELLIS_SALSIFY_ID_ATTRIBUTE];
            }, $salsifyProductsById), true);

        } catch (\Exception $e) {
            $this->_notifySyncError($e);
            throw new LocalizedException(new Phrase($e->getMessage()));
        }

        $this->_notifySyncFinished();
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

        $this->_updatedProducts = $this->_updateProducts($salsifyProductsById);
        $this->_createProducts($salsifyProductsById, $this->_updatedProducts);
        $this->_updateConfigurables($salsifyProductsById);

        $this->_updateProductRelations($salsifyProductsById);

        $this->_syncExecution->end();
        $time = $this->_syncExecution->getFullStats();
        $this->_logger->info("--- UPSERT FINISHED: " . $time['end_time']);
        $this->_logger->info("--- TOTAL EXECUTION TIME: " . $time['execution_time'] . " seconds.");
        $this->_logger->info("--- FINISHED UPSERT ---");
    }


    // PROTECTED METHODS

    /**
     * @param Product $product
     * @param array   $salsifyRecord
     */
    protected function _updateProductMediaGallery(Product $product, $salsifyRecord)
    {
        $i = 0;
        if ($this->_data->getVideoEnabled()) {
            $this->processVideoGalleryEntries($product, $salsifyRecord);
        }

        // If media gallery not enabled, return:
        if (!$this->_data->getMediaGalleryEnabled()) {
            return;
        }

        $this->_logger->info("Updating product media for SKU: {$product->getSku()}");

        $salsifyAssets = [];

        // All media_gallery images first because they have no tags
        // If a media_gallery image is also a tagged image, it will be overwritten in the next section
        if (isset($salsifyRecord['media_gallery'])) {
            $mediaGallery = $salsifyRecord['media_gallery'];
            if (!is_array($mediaGallery)) {
                $mediaGallery = explode(',', $mediaGallery);
            }

            foreach ($mediaGallery as $i => $image) {
                if (!empty($image)) {
                    // If image is an array, then it's an object from salsify probably containing the image label/hash
                    if (is_array($image)) {
                        // If url is not set, don't add this image
                        if (empty($image['url'])) {
                            continue;
                        }

                        $asset = $image;
                    } else {
                        $asset = [
                            'url' => $image,
                        ];
                    }

                    $asset['position'] = $i;
                    $salsifyAssets[]   = $asset;
                }
            }
        }

        // Add images with specific tags based on image mapping field in admin
        // If there is any overlap between tagged images and media gallery images, the tagged images will take precedence
        $imageMapping = $this->_data->getImageMapping();
        $i++;
        if (!is_array($imageMapping)) {
            return;
        }
        foreach ($imageMapping as $salsifyImageField => $tags) {
            if (isset($salsifyRecord[ $salsifyImageField ])) {
                // If image is an array, then it's an object from salsify probably containing the image label/hash
                if (is_array($salsifyRecord[ $salsifyImageField ])) {
                    // If url is not set, don't add this image
                    if (empty($salsifyRecord[ $salsifyImageField ]['url'])) {
                        continue;
                    }

                    $asset = $salsifyRecord[ $salsifyImageField ];
                } else {
                    $asset = [
                        'url' => $salsifyRecord[ $salsifyImageField ],
                    ];
                }

                $asset['tags']     = (array)$tags;
                $asset['position'] = $i++;
                $salsifyAssets[]   = $asset;
            }
        }

        if (count($salsifyAssets)) {
            $this->_processMediaGalleryEntries($product, $salsifyAssets);
        }
    }

    /**
     * Create attributes in Magento by grabbing all attributes from the Salsify API,
     * iterating over them and requesting metadata about them from the API,
     * then creating only the attributes which have a specific metadata attached to them.
     *
     * @see https://growwithtrellis.atlassian.net/wiki/spaces/SAL/pages/725614601/Getting+metadata
     */
    protected function _createSalsifyAttributes()
    {
        if ($this->_data->getCreateAttributesDuringSync()) {
            $this->_logger->info("Starting creating attributes from Salsify.");
            $client = $this->getClient();

            // Grab all properties in batches of $perPage
            $allProperties = [];
            $perPage = 200;
            $page = 1;
            $moreResults = true;

            $this->_logger->info("Getting properties from Salsify in batches of {$perPage}.");
            while ($moreResults) {
                $properties = $client->getProperties($page, $perPage);
                $this->_logger->info("Got " . count($properties['properties']) . " properties in page {$page}.");

                if (!isset($properties['properties']) || count($properties['properties']) !== $perPage) {
                    $moreResults = false;
                }

                $allProperties = array_merge($allProperties, $properties['properties']);
                $page++;
            }

            $this->_logger->info("Got a total of " . count($allProperties) . " properties from Salsify.");

            $salsifyAttributeIds = [];
            foreach ($allProperties as $salsifyAttribute) {
                $salsifyAttributeIds[] = $salsifyAttribute['external_id'];
            }

            // Get all properties by ID in batches of $batchSize
            $batchSize = 100;
            $allSalsifyAttributes = [];
            $this->_logger->info("Getting property metadata from Salsify in batches of {$batchSize}.");
            for ($i = 0; $i < count($salsifyAttributeIds); $i += $batchSize) {
                $batch = array_slice($salsifyAttributeIds, $i, $batchSize);
                $propertiesInBatch = $client->getPropertiesByIds($batch);
                $this->_logger->info("Got batch of " . count($propertiesInBatch) . " property metadata from Salsify");

                $allSalsifyAttributes = array_merge($allSalsifyAttributes, $propertiesInBatch);
            }

            $this->_logger->info("Got a total of " . count($allSalsifyAttributes) . " property metadata from Salsify");

            $this->_productAttributes->createProductAttributes($allSalsifyAttributes);
        }
    }
}
