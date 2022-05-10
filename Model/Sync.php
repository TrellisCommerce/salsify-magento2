<?php
/**
 * @copyright Copyright © 2020 Trellis, LLC. All rights reserved.
 */

namespace Trellis\Salsify\Model;

use Magento\Bundle\Api\Data\LinkInterfaceFactory;
use Magento\Bundle\Api\Data\OptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Gallery\Processor as GalleryProcessor;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Api\Data\SampleInterfaceFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Glob;
use Magento\Framework\Notification\NotifierInterface as NotifierPool;
use Magento\ProductVideo\Model\Product\Attribute\Media\ExternalVideoEntryConverter;
use Trellis\Salsify\Controller\Adminhtml\System\Config\Debug\Debug;
use Trellis\Salsify\Helper\Config as FeedConfig;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Helper\Product as ProductHelper;
use Trellis\Salsify\Helper\ProductFeed;
use Trellis\Salsify\Model\Config\Rabbitmq;
use Trellis\Salsify\Logger\Logger;
use Trellis\Salsify\Model\Product\Gallery\Video\Processor;
use Trellis\Salsify\Model\Product\Swatch;
use Trellis\Salsify\Model\Queue\PushSalsifyProductsToQueue;
use Trellis\Salsify\Model\Sync\CategoryMapping;
use Trellis\Salsify\Model\Sync\ProductAttributes;
use Trellis\Salsify\Model\Sync\ProductLinks;
use Trellis\Salsify\Model\Catalog\CategoryProcessor;

/**
 * Class Sync
 *
 * @package Trellis\Salsify\Model
 */
abstract class Sync
{

    const MAGENTO_DEFAULT_ATTR_SET_FOR_CATALOG_PRODUCTS = 4;

    /** ===== SALSIFY SYSTEM PROPERTIES ===== */
    /**
     * @var string
     */
    const SALSIFY_PROPERTY_PREFIX = 'salsify:';
    const SALSIFY_ID_KEY = self::SALSIFY_PROPERTY_PREFIX . 'id';
    const SALSIFY_LAST_UPDATED = self::SALSIFY_PROPERTY_PREFIX . 'updated_at';
    const SALSIFY_URL_KEY = self::SALSIFY_PROPERTY_PREFIX . 'url';
    const SALSIFY_FILE_NAME = self::SALSIFY_PROPERTY_PREFIX . 'filename';
    const SALSIFY_PARENT_KEY = self::SALSIFY_PROPERTY_PREFIX . 'parent_id';
    const SALSIFY_DEFAULT_SKU = 'sku';

    /** ===== TRELLIS CUSTOM PROPERTIES ===== */

    const TRELLIS_SALSIFY_ID_ATTRIBUTE = 'trellis_salsify_id';
    const TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE = 'parent_id';
    const TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE = 'product_type';
    const TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE = 'attribute_set_code';
    const TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE = 'configurable_variation_labels';
    const TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE = 'category_ids';
    const TRELLIS_SALSIFY_MEDIA_GALLERY_ATTRIBUTE = 'media_gallery';
    const TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE = 'constant_system_attribute';
    const TRELLIS_SALSIFY_BUNDLED_SKUS = 'bundled_skus';
    const TRELLIS_SALSIFY_GROUPED_SKUS = 'grouped_skus';
    const TRELLIS_SALSIFY_RELATED_SKUS = 'related_skus';
    const TRELLIS_SALSIFY_CROSSSELL_SKUS = 'crosssell_skus';
    const TRELLIS_SALSIFY_UPSELL_SKUS = 'upsell_skus';
    const TRELLIS_SALSIFY_WEBSITE_ID = 'magento_website_codes';

    const TRELLIS_SALSIFY_ATTRIBUTES = [
        self::TRELLIS_SALSIFY_ID_ATTRIBUTE,
        self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE,
        self::TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE,
        self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE,
        self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE,
        self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE,
        self::TRELLIS_SALSIFY_MEDIA_GALLERY_ATTRIBUTE,
        self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE,
        self::TRELLIS_SALSIFY_BUNDLED_SKUS,
        self::TRELLIS_SALSIFY_GROUPED_SKUS,
        self::TRELLIS_SALSIFY_RELATED_SKUS,
        self::TRELLIS_SALSIFY_CROSSSELL_SKUS,
        self::TRELLIS_SALSIFY_UPSELL_SKUS,
    ];

    const PARADOXLABS_SUBSCRIPTION_INTERVALS_GRID = 'subscription_intervals_grid';

    const PARADOXLABS_SUBSCRIPTION_INTERVALS_KEY = 'intervals';

    /**
     *
     */
    const TRELLIS_SALSIFY_LAST_UPDATED = 'trellis_salsify_last_updated';
    const XML_PATH_YOUTUBE_API = 'catalog/product_video/youtube_api_key';

    /**
     * @var string
     */
    protected $_mappedSku;

    /** @var Client */
    protected $_client;
    /**
     * @var CollectionFactory
     */
    protected $_productCollectionFactory;
    /**
     * @var ProductInterfaceFactory
     */
    protected $_productFactory;
    /**
     * @var AttributeRepositoryInterface
     */
    protected $_attributeRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;
    /**
     * @var ProductHelper $_productHelper
     */
    protected $_productHelper;
    /**
     * @var ProductFeed
     */
    protected $_productFeed;
    /**
     * @var ProductAttributeMediaGalleryManagementInterface
     */
    protected $_mediaGalleryManagement;
    /**
     * @var Debug
     */
    protected $_debug;
    /**
     * @var FeedConfig
     */
    protected $_feedConfig;
    /**
     * @var Data
     */
    protected $_data;
    /**
     * @var ProductAttributeMediaGalleryEntryInterfaceFactory
     */
    protected $_mediaGalleryEntryFactory;
    /**
     * @var CategoryCollectionFactory
     */
    protected $_categoryCollectionFactory;
    /**
     * @var \Magento\Catalog\Api\CategoryLinkManagementInterface
     */
    protected $_categoryLinkManagement;
    /**
     * @var CategoryFactory
     */
    protected $_categoryFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManagementInterface;
    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;
    /**
     * @var \Trellis\Salsify\Logger\Logger
     */
    protected $_logger;
    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute
     */
    protected $_eavAttribute;
    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $_directoryList;
    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory
     */
    protected $_attributeSetCollectionFactory;
    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $_eavConfig;
    /**
     * @var \Trellis\Salsify\Logger\SyncExecution
     */
    protected $_syncExecution;
    /**
     * @var \Magento\Catalog\Api\Data\ProductLinkInterfaceFactory
     */
    protected $_productLinkInterfaceFactory;
    /**
     * @var \Magento\Bundle\Api\Data\OptionInterfaceFactory
     */
    protected $_optionInterface;
    /**
     * @var \Magento\Bundle\Api\Data\LinkInterfaceFactory
     */
    protected $_linkInterfaceFactory;
    /**
     * @var \Magento\Catalog\Model\Product\Option
     */
    protected $_productOption;
    /**
     * @var \Magento\Downloadable\Api\Data\LinkInterfaceFactory
     */
    protected $_fileLinkInterfaceFactory;
    /**
     * @var \Magento\Downloadable\Api\Data\SampleInterfaceFactory
     */
    protected $_sampleInterfaceFactory;
    /**
     * @var Product
     */
    protected $_product;
    /**
     * @var MediaDownloadManager
     */
    protected $_downloadManager;
    /**@var ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var Processor */
    protected $_videoProcessor;
    /**
     * @var CategoryMapping
     */
    protected $_categoryMapping;

    /** @var ProductLinks $productLinks */
    protected $productLinks;
    /**
     * @var Factory
     */
    protected $_optionsFactory;

    /**
     * @var ProductAttributes
     */
    protected $_productAttributes;

    /** @var NotifierPool */
    protected $_notifierPool;

    /** @var Glob */
    protected $_glob;

    /** @var Swatch */
    protected $_swatch;

    /** @var GalleryProcessor */
    protected $_mediaGalleryProcessor;

    protected $_updatedProducts = [];
    protected $_createdProducts = [];
    protected $_failedProducts = [];
    protected $_downloadedImages = [];
    protected $_magentoAttributeFrontendTypes;

    /**
     * @var ProductLinks
     */
    private $_productLinks;

    /**
     * @var PushSalsifyProductsToQueue
     */
    private $pushSalsifyProductsToQueue;
    /**
     * @var Rabbitmq
     */
    private $rabbitmq;

    /** @var CategoryProcessor $categoryProcessor */
    private $categoryProcessor;

    /**
     * Sync constructor.
     *
     * @param ClientFactory                                                           $clientFactory
     * @param CollectionFactory                                                       $productCollectionFactory
     * @param AttributeRepositoryInterface                                            $attributeRepository
     * @param ProductInterfaceFactory                                                 $productFactory
     * @param SearchCriteriaBuilder                                                   $searchCriteriaBuilder
     * @param ProductHelper                                                           $productHelper
     * @param ProductFeed                                                             $productFeed
     * @param Debug                                                                   $debug
     * @param FeedConfig                                                              $feedConfig
     * @param Data                                                                    $data
     * @param ProductAttributeMediaGalleryManagementInterface                         $mediaGalleryManagement
     * @param ProductAttributeMediaGalleryEntryInterfaceFactory                       $mediaGalleryEntryFactory
     * @param CategoryCollectionFactory                                               $categoryCollectionFactory
     * @param CategoryFactory                                                         $categoryFactory
     * @param \Magento\Catalog\Api\CategoryLinkManagementInterface                    $categoryLinkManagement
     * @param \Magento\Store\Model\StoreManagerInterface                              $storeManagerInterface
     * @param ProductRepositoryInterface                                              $productRepository
     * @param Logger                                                                  $logger
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute                       $eavAttribute
     * @param \Magento\Framework\Filesystem\DirectoryList                             $directoryList
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $attributeSetCollectionFactory
     * @param \Magento\Eav\Model\Config                                               $eavConfig
     * @param \Trellis\Salsify\Logger\SyncExecution                                   $syncExecution
     * @param ProductLinkInterfaceFactory                                             $productLinkInterfaceFactory
     * @param OptionInterfaceFactory                                                  $optionInterface
     * @param LinkInterfaceFactory                                                    $linkInterfaceFactory
     * @param Product\Option                                                          $productOption
     * @param \Magento\Downloadable\Api\Data\LinkInterfaceFactory                     $fileLinkInterfaceFactory
     * @param SampleInterfaceFactory                                                  $sampleInterfaceFactory
     * @param Product                                                                 $product
     * @param MediaDownloadManager                                                    $downloadManager
     * @param ScopeConfigInterface                                                    $scopeConfig
     * @param Processor                                                               $videoProcessor
     * @param CategoryMapping                                                         $categoryMapping
     * @param ProductLinks                                                            $productLinks
     * @param ProductAttributes                                                       $productAttributes
     * @param Factory                                                                 $optionsFactory
     * @param NotifierPool                                                            $notifierPool
     * @param Glob                                                                    $glob
     * @param Swatch                                                                  $swatch
     * @param GalleryProcessor                                                        $mediaGalleryProcessor
     * @param PushSalsifyProductsToQueue                                              $pushSalsifyProductsToQueue
     * @param Rabbitmq                                                                $rabbitmq
     * @param CategoryProcessor                                                       $categoryProcessor
     */
    public function __construct(
        ClientFactory $clientFactory,
        CollectionFactory $productCollectionFactory,
        AttributeRepositoryInterface $attributeRepository,
        ProductInterfaceFactory $productFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductHelper $productHelper,
        ProductFeed $productFeed,
        Debug $debug,
        FeedConfig $feedConfig,
        Data $data,
        ProductAttributeMediaGalleryManagementInterface $mediaGalleryManagement,
        ProductAttributeMediaGalleryEntryInterfaceFactory $mediaGalleryEntryFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryFactory $categoryFactory,
        \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagement,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        ProductRepositoryInterface $productRepository,
        \Trellis\Salsify\Logger\Logger $logger,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute $eavAttribute,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $attributeSetCollectionFactory,
        \Magento\Eav\Model\Config $eavConfig,
        \Trellis\Salsify\Logger\SyncExecution $syncExecution,
        \Magento\Catalog\Api\Data\ProductLinkInterfaceFactory $productLinkInterfaceFactory,
        \Magento\Bundle\Api\Data\OptionInterfaceFactory $optionInterface,
        \Magento\Bundle\Api\Data\LinkInterfaceFactory $linkInterfaceFactory,
        \Magento\Catalog\Model\Product\Option $productOption,
        \Magento\Downloadable\Api\Data\LinkInterfaceFactory $fileLinkInterfaceFactory,
        \Magento\Downloadable\Api\Data\SampleInterfaceFactory $sampleInterfaceFactory,
        Product $product,
        MediaDownloadManager $downloadManager,
        ScopeConfigInterface $scopeConfig,
        Processor $videoProcessor,
        CategoryMapping $categoryMapping,
        ProductLinks $productLinks,
        ProductAttributes $productAttributes,
        Factory $optionsFactory,
        NotifierPool $notifierPool,
        Glob $glob,
        Swatch $swatch,
        GalleryProcessor $mediaGalleryProcessor,
        PushSalsifyProductsToQueue $pushSalsifyProductsToQueue,
        Rabbitmq $rabbitmq,
        CategoryProcessor $categoryProcessor
    ) {
        $this->_client = $clientFactory->create();
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productFactory = $productFactory;
        $this->_attributeRepository = $attributeRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_productHelper = $productHelper;
        $this->_productFeed = $productFeed;
        $this->_debug = $debug;
        $this->_feedConfig = $feedConfig;
        $this->_data = $data;
        $this->_mediaGalleryManagement = $mediaGalleryManagement;
        $this->_mediaGalleryEntryFactory = $mediaGalleryEntryFactory;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_categoryLinkManagement = $categoryLinkManagement;
        $this->_categoryFactory = $categoryFactory;
        $this->_storeManagementInterface = $storeManagerInterface;
        $this->_productRepository = $productRepository;
        $this->_logger = $logger;
        $this->_eavAttribute = $eavAttribute;
        $this->_directoryList = $directoryList;
        $this->_attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->_eavConfig = $eavConfig;
        $this->_syncExecution = $syncExecution;
        $this->_productLinkInterfaceFactory = $productLinkInterfaceFactory;
        $this->_optionInterface = $optionInterface;
        $this->_linkInterfaceFactory = $linkInterfaceFactory;
        $this->_productOption = $productOption;
        $this->_fileLinkInterfaceFactory = $fileLinkInterfaceFactory;
        $this->_sampleInterfaceFactory = $sampleInterfaceFactory;
        $this->_product = $product;
        $this->_downloadManager = $downloadManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_videoProcessor = $videoProcessor;
        $this->_categoryMapping = $categoryMapping;
        $this->_productLinks = $productLinks;
        $this->_productAttributes = $productAttributes;
        $this->_optionsFactory = $optionsFactory;
        $this->_notifierPool = $notifierPool;
        $this->_glob = $glob;
        $this->_swatch = $swatch;
        $this->_mediaGalleryProcessor = $mediaGalleryProcessor;
        $this->pushSalsifyProductsToQueue = $pushSalsifyProductsToQueue;
        $this->rabbitmq = $rabbitmq;
        $this->categoryProcessor = $categoryProcessor;
        $propertyMap = $this->_productFeed->getPropertyMapping();
        $this->_mappedSku = array_search('sku', $propertyMap) ?? self::SALSIFY_DEFAULT_SKU;
    }

    /**
     * @return \Trellis\Salsify\Model\Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * @param      $productIds
     * @param bool $inclusive
     */
    public function deleteProducts($productIds, $inclusive = false)
    {
        if (!$this->_data->getDeleteEnabled()) {
            return;
        }
        $this->_logger->info("--- BEGIN DELETING EXISTING PRODUCTS ---");
        // "inclusive" mode will delete any IDs not contained within $productIds, "exclusive" mode will do the opposite (whack only the items contained with productIds):
        $filter = [];
        $filter[$inclusive ? 'nin' : 'in'] = $productIds;
        $filter['notnull'] = true;
        // Delete salsify products that are not in the original list of salsify IDs -- these are not in the feed:
        $magentoProductsToDelete = $this->_productCollectionFactory->create();
        $magentoProductsToDelete->addAttributeToSelect('*');
        $magentoProductsToDelete->addAttributeToFilter(self::TRELLIS_SALSIFY_ID_ATTRIBUTE, $filter);
        $magentoProductsToDelete->walk('delete');
        $this->_logger->info("--- FINISHED DELETING EXISTING PRODUCTS ---");
    }

    /**
     * @param Product $product
     * @param array   $salsifyRecord
     *
     * @return Product
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processVideoGalleryEntries(Product $product, $salsifyRecord)
    {
        $videoField = $this->_data->getVideoField();
        if (!$videoField) {
            $this->_logger->addNotice('--- VIDEO PROPERTY IS NOT SET ---');
            $this->_logger->addNotice('--- PRODUCT SKU: ' . $product->getSku());
            $this->_logger->addNotice('--- METHOD: ' . __METHOD__);
        }

        if ($videoField && array_key_exists($videoField, $salsifyRecord)) {
            $options = $salsifyRecord[$videoField];
            if ($this->_isJsonValid($options)) {
                $options = json_decode($options);
                foreach ($options as $option) {
                    if (!is_array($option)) {
                        $option = json_decode(json_encode($option), true);
                    }
                    $existingGallery = $this->_mediaGalleryManagement->getList($product->getSku());
                    if ($existingGallery) {
                        $productVideos = [];
                        foreach ($existingGallery as $image) {
                            $imageData = $image->getData();
                            if (isset($imageData['media_type']) && $imageData['media_type'] == 'external-video') {
                                try {
                                    $videoUrl = $image->getExtensionAttributes()->getVideoContent()->getVideoUrl();
                                } catch (\Exception $e) {
                                    $this->logger->addError($e->getMessage());
                                }
                                $productVideos[] = $videoUrl;
                            }
                        }
                        if (in_array($option['video_url'], $productVideos)) {
                            // video already exists for product
                            continue;
                        }
                    }
                    $videoData = [
                        'video_title'       => $option['video_title'],
                        'video_description' => $option['video_description'],
                        'video_url'         => $option['video_url'],
                        'thumbnail'         => $this->_processVideoThumbnail($option['video_url'])['filePath'],
                        'video_provider'    => $this->_processVideoProvider($option['video_url']),
                        'video_metadata'    => null,
                        'media_type'        => ExternalVideoEntryConverter::MEDIA_TYPE_CODE
                    ];
                    $mediaAttribute = [];
                    if ($option['video_mediaattribute']) {
                        $mediaAttribute = explode(',', $option['video_mediaattribute']);
                    }
                    if ($product->hasGalleryAttribute()) {
                        $this->_videoProcessor->addVideo(
                            $product,
                            $videoData,
                            $mediaAttribute
                        );
                    }
                    $this->_downloadManager->delete($videoData['thumbnail']);
                }
            }
            $this->_logger->addCritical('INVALID JSON!');
            $this->_logger->addCritical('--- VIDEO PROPERTY JSON INVALID ---');
            $this->_logger->addCritical('--- PRODUCT SKU: ' . $product->getSku());
            $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
        }

        return $product;
    }

    // PROTECTED METHODS

    /**
     * @param array $salsifyRecords
     *
     * @return array
     */
    protected function _getSalsifyProductsById(array $salsifyRecords)
    {
        $skuKeys = array_map(function ($salsifyRecord)  {
            return $salsifyRecord[$this->_mappedSku] ?? ($salsifyRecord[self::SALSIFY_DEFAULT_SKU] ?? null);
        }, $salsifyRecords);

        try {
            $magentoProducts = $this->_getProductsBySkuKeys($skuKeys);
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
        }
        $salsifyProductsById = [];
        foreach ($salsifyRecords as $salsifyRecord) {
            $productSku = $salsifyRecord[$this->_mappedSku] ?? ($salsifyRecord[self::SALSIFY_DEFAULT_SKU] ?? null);
            if(is_null($productSku))
                continue;
            $product = $magentoProducts->getItemByColumnValue('sku', $productSku);
            if ($product && $product->getSku() !== $productSku) {
                $this->_logger->info("Product with sku {$productSku} already exists! Skipping product {$productSku}.");
            } else {
                $salsifyProductsById[$salsifyRecord[self::TRELLIS_SALSIFY_ID_ATTRIBUTE]] = $salsifyRecord;
            }
        }
        return $salsifyProductsById;
    }

    /**
     * @param array $salsifyRecords
     *
     * @return array
     */
    protected function _getSalsifyProductsBySku(array $salsifyRecords)
    {
        $salsifyProductsBySku = [];
        foreach ($salsifyRecords as $salsifyRecord) {
            $productSku = $salsifyRecord[$this->_mappedSku] ?? ($salsifyRecord[self::SALSIFY_DEFAULT_SKU] ?? null);
            if(is_null($productSku))
                continue;
            $salsifyProductsBySku[$productSku] = $salsifyRecord;
        }

        return $salsifyProductsBySku;
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
            return $salsifyRecord[$this->_mappedSku] ?? ($salsifyRecord[self::SALSIFY_DEFAULT_SKU] ?? null);
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

    /**
     * Add a notification message when a sync finishes.
     */
    protected function _notifySyncFinished()
    {
        $countUpdated = count($this->_updatedProducts);
        $countCreated = count($this->_createdProducts);

        $this->_notifierPool->addNotice(
            "Sync Finished",
            "Successfully updated {$countUpdated} products, created {$countCreated} products."
        );
    }

    /**
     * Add a notification message when a sync fails.
     *
     * @param \Exception $exception
     */
    protected function _notifySyncError($exception)
    {
        $this->_notifierPool->addCritical("Error while syncing from Salsify", $exception->getMessage());
    }

    /** GET PRODUCTS BY SALSIFY IDS **/

    /**
     * @param $salsifyIds
     *
     * @return array
     */
    protected function _getProductIdsFromSalsifyIds($salsifyIds)
    {
        if ($result = $this->_getProductsBySalsifyIds($salsifyIds)) {
            return $result->getAllIds();
        }

        return [];
        /*return array_map(function ($product) {
            return $product->getId();
        }, $this->_getProductsBySalsifyIds($salsifyIds));*/
    }

    /**
     * @param $salsifyIds
     *
     * @return mixed
     */
    protected function _getProductsBySalsifyIds($salsifyIds)
    {
        // Filter to already existing salsify products, and update them with the new content:
        $magentoProducts = $this->_productCollectionFactory->create();
        $magentoProducts->addAttributeToSelect('*');
        $magentoProducts->addAttributeToFilter(self::TRELLIS_SALSIFY_ID_ATTRIBUTE, ["in" => $salsifyIds]);

        //return $magentoProducts->getItems();
        if ($magentoProducts->getSize()) {
            return $magentoProducts;
        }

        return false;
    }

    /**
     * @param $salsifyId
     *
     * @return false|ProductInterface
     */
    protected function _getProductBySalsifyId($salsifyId)
    {
        $collection = $this->_productCollectionFactory->create();
        $collection->addAttributeToSelect('sku');
        $collection->addAttributeToFilter(self::TRELLIS_SALSIFY_ID_ATTRIBUTE, $salsifyId);
        $collection->setPageSize(1);

        try {
            //$this->_productRepository->getList($searchCriteria)
            if ($p = current($collection->getData())) {
                $sku = $p['sku'];
                $result = $this->_productRepository->get($sku);
            }
            $this->_logger->info("Salsify parent (base/configurable) product: " . $result->getSku());

            return $result;
        } catch (NoSuchEntityException $exception) {
            $this->_logger->error($exception->getMessage());
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
        }

        return false;
    }

    /**
     * @param $salsifyId
     *
     * @return mixed
     * @throws \Exception
     */
    protected function _getProductsBySkuKeys($skuKeys)
    {
        // Filter to already existing salsify products, and update them with the new content:
        $attributes = [
            ['attribute' => 'sku', 'in' => $skuKeys],
        ];

        $magentoProducts = $this->_productCollectionFactory->create();
        $magentoProducts->addAttributeToSelect('*');
        $magentoProducts->addAttributeToFilter($attributes);

        return $magentoProducts;
    }

    /**
     * @param $salsifyProductsById
     *
     * @return array
     * @throws CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _updateProducts($salsifyProductsById)
    {
        $this->_logger->info('--- BEGIN EXISTING PRODUCT UPDATES ---');

        $magentoProductsToUpdate = $this->_getProductsToUpdate($salsifyProductsById);
        $total = count($magentoProductsToUpdate);
        $updatedProducts = [];

        $salsifyProductsBySku = $this->_getSalsifyProductsBySku($salsifyProductsById);

        $counter = 1;
        // check all products for metadata. there is a likely chance this could run on existing products that don't have
        // trellis metadata. We need to match the sku of products from Salsify, and write this data accordingly.

        foreach ($magentoProductsToUpdate as $magentoProduct) {
            $this->_logger->info("- Updating product {$counter} of {$total}...");
            ++$counter;

            // If no salsify id is set on the product already, then we need to set that before doing anything else
            if (!$magentoProduct->getData(self::TRELLIS_SALSIFY_ID_ATTRIBUTE)) {
                $salsifyProduct = $salsifyProductsBySku[$magentoProduct->getSku()];
                $salsifyId = $salsifyProduct[self::TRELLIS_SALSIFY_ID_ATTRIBUTE];
                $magentoProduct->setData(self::TRELLIS_SALSIFY_ID_ATTRIBUTE, $salsifyId);

                $this->_logger->info("Setting salsify id {$salsifyId} for {$magentoProduct->getSku()}");
            }

            $salsifyId = $magentoProduct->getData(self::TRELLIS_SALSIFY_ID_ATTRIBUTE);
            $salsifyRecord = $salsifyProductsById[$salsifyId];
            $updatedProducts[] = $salsifyId;
            $lastUpdated = $magentoProduct->getData(self::TRELLIS_SALSIFY_LAST_UPDATED);

            // SKIP UPDATING IF DATE IS OLD:
            if (isset($salsifyRecord[self::TRELLIS_SALSIFY_LAST_UPDATED])) {
                $ignoreTimestampAndAlwaysUpdate = $this->_data->getIgnoreTimestampAndAlwaysUpdate();
                $timestampIsNewer = date($lastUpdated) >= date($salsifyRecord[self::TRELLIS_SALSIFY_LAST_UPDATED]);
                if (!$ignoreTimestampAndAlwaysUpdate && $timestampIsNewer) {
                    continue;
                }
            }

            // Pull out some data we use to set manually
            $categoryIds = isset($salsifyRecord[self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE]) ? $salsifyRecord[self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE] : [];
            if (!is_array($categoryIds)) {
                $categoryIds = $this->categoryProcessor->processCategories($categoryIds);
            }
            unset($salsifyRecord[self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE]);

            try {
                $this->_updateProductAttributeSetId($magentoProduct, $salsifyRecord);
            } catch (\Exception $exception) {
                $this->_logger->error($exception->getMessage());
            }

            $magentoProduct = $this->_updateProductAttributes($magentoProduct, $salsifyRecord);
            $this->_updateCustomOptions($magentoProduct, $salsifyRecord);
            $this->_updateProductMediaGallery($magentoProduct, $salsifyRecord);

            try {
                $this->_productRepository->save($magentoProduct);
            } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
                $this->_logger->error(__('We couldn\'t save SKU %1: %2.', $magentoProduct->getSku(), $e->getMessage()));
            } catch (\Exception $exception) {
                $this->_logger->error($exception->getMessage());
            }

            $this->_updateCategories($magentoProduct, $categoryIds);
        }

        foreach ($this->_downloadedImages as $downloadedImage) {
            $this->_downloadManager->delete($downloadedImage);
        }

        $this->_logger->info("--- EXISTING PRODUCT UPDATES COMPLETE ---");

        return $updatedProducts;
    }

    /**
     * @param      $salsifyAttributes
     * @param      $salsifyProductsById
     * @param null $alreadyProcessed
     */
    protected function _createProducts(array $salsifyProductsById, array $alreadyProcessed = [])
    {
        if (count($salsifyProductsById) !== count($alreadyProcessed)) {
            $this->_logger->info("--- BEGIN CREATING NEW PRODUCTS ---");

            // Default to creating all Salsify records:
            $salsifyRecordsToCreate = $salsifyProductsById;

            // If we've already processed any items, the recreate $salsifyRecordsToCreate
            if (is_array($alreadyProcessed)) {
                $salsifyRecordsToCreate = [];
                foreach ($salsifyProductsById as $salsifyRecord) {
                    if (!in_array($salsifyRecord[self::TRELLIS_SALSIFY_ID_ATTRIBUTE], $alreadyProcessed)) {
                        $salsifyRecordsToCreate[] = $salsifyRecord;
                    }
                }
            }

            $counter = 1;
            $total = count($salsifyRecordsToCreate);

            foreach ($salsifyRecordsToCreate as $salsifyRecord) {
                $this->_logger->info("- Creating product " . $counter . " of " . $total . "...");

                /** @var $magentoProduct Product */
                $magentoProduct = $this->_productFactory->create();

                try {
                    $this->_updateProductAttributeSetId($magentoProduct, $salsifyRecord);
                    $this->_logger->info("Product {$counter} attribute set: {$magentoProduct->getAttributeSetId()}");
                } catch (\Exception $exception) {
                    $this->_logger->error($exception->getMessage());
                }

                if (array_key_exists(self::TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE, $salsifyRecord)) {
                    $magentoProduct->setTypeId($salsifyRecord[self::TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE]);
                } else {
                    $magentoProduct->setTypeId(Type::DEFAULT_TYPE);
                }

                // Pull out some data we use to set manually
                $categoryIds = isset($salsifyRecord[self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE]) ? $salsifyRecord[self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE] : [];
                if (!is_array($categoryIds)) {
                    $categoryIds = $this->categoryProcessor->processCategories($categoryIds);
                }
                unset($salsifyRecord[self::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE]);

                $magentoProduct = $this->_updateProductAttributes($magentoProduct, $salsifyRecord);

                if (array_key_exists($magentoProduct->getSku(), $this->_createdProducts)) {
                    $this->_updateProductMediaGallery($magentoProduct, $salsifyRecord);
                }

                try {
                    $magentoProduct = $this->_productRepository->save($magentoProduct);
                    $this->_createdProducts[$magentoProduct->getSku()] = $salsifyRecord;
                } catch (CouldNotSaveException $exception) {
                    $this->_failedProducts[$magentoProduct->getSku()] = $exception->getMessage();
                    $this->_logger->addCritical('--- ERROR SAVING PRODUCT ---');
                    $this->_logger->addCritical("--- PRODUCT SKU: {$magentoProduct->getSku()}");
                    $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
                    $this->_logger->addCritical('--- MESSAGE: ' . json_encode($exception->getMessage()));
                    $this->_logger->addCritical('--- START STACK TRACE ---');
                    $this->_logger->addError($exception->getTraceAsString());
                    $this->_logger->addCritical('--- END STACK TRACE ---');
                } catch (\Exception $exception) {
                    $this->_failedProducts[$magentoProduct->getSku()] = $exception->getMessage();
                    $this->_logger->addCritical('--- ERROR SAVING PRODUCT ---');
                    $this->_logger->addCritical("--- PRODUCT SKU: {$magentoProduct->getSku()}");
                    $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
                    $this->_logger->addCritical('--- MESSAGE: ' . json_encode($exception->getMessage()));
                    $this->_logger->addCritical('--- START STACK TRACE ---');
                    $this->_logger->addError($exception->getTraceAsString());
                    $this->_logger->addCritical('--- END STACK TRACE ---');
                }
                $this->_updateCategories($magentoProduct, $categoryIds);
                $counter++;
            }

            $this->_logger->info("Deleting downloaded images");
            foreach ($this->_downloadedImages as $downloadedImage) {
                $this->_downloadManager->delete($downloadedImage);
            }
            $this->_logger->info("--- FINISHED CREATING NEW PRODUCTS ---");
            if (!empty($this->_failedProducts)) {
                $this->_logger->info("--- FAILED PRODUCT REPORT ---");
                foreach ($this->_failedProducts as $sku => $error) {
                    $this->_logger->info("{$sku}: {$error}");
                }
            }
        } else {
            $this->_logger->info("--- NO NEW PRODUCTS TO CREATE ---");
        }
    }

    /**
     * @param $magentoProduct
     * @param $salsifyRecord
     *
     * @return mixed
     */
    protected function _updateCustomOptions($magentoProduct, $salsifyRecord)
    {
        $customOptionsField = $this->_data->getCustomOptionsField();
        if (array_key_exists($customOptionsField, $salsifyRecord) && $this->_data->getCustomOptionsEnabled()) {
            $this->_logger->info('customOptionsField: ' . json_encode($customOptionsField));
            $this->_logger->info("customOptionsField Value: {$salsifyRecord[ $customOptionsField ]}");

            $options = $salsifyRecord[$customOptionsField];

            if ($this->_isJsonValid($options)) {
                $this->_logger->info('VALID JSON!');
                $options = json_decode($options);
                $magentoProduct->setHasOptions(1);
                $magentoProduct->setCanSaveCustomOptions(true);
                $this->_logger->info('OPTIONS: ' . json_encode($options));
                foreach ($options as $option) {
                    if (!is_array($option)) {
                        $option = json_decode(json_encode($option), true);
                    }
                    $customOptions = $this->_productOption
                        ->setProductId($magentoProduct->getId())
                        ->setStoreId($magentoProduct->getStoreId())
                        ->addData($option);

                    $customOptions->save();
                    $magentoProduct->addOption($customOptions);
                    $this->_logger->info('OPTION: ' . json_encode($option));
                    foreach ($option as $key => $values) {
                        //todo setup options here
                        $this->_logger->info("{$key} => " . json_encode($values));
                        if (is_array($values)) {
                            $this->_logger->info("VALUES ARRAY");
                            $this->_logger->info(json_encode($values));
                            foreach ($values as $value) {
                                $this->_logger->info("VALUE");
                                $this->_logger->info(json_encode($value));
                                foreach ($value as $valueArrayKey => $valueArrayValue) {
                                    //todo setup values here
                                    $this->_logger->info("{$valueArrayKey} => {$valueArrayValue}");
                                }
                            }
                        }
                    }
                }
            }
            $this->_logger->info('INVALID JSON!');
        }

        return $magentoProduct;
    }

    /** PRODUCT LINKS **/

    /**
     * @param $magentoProduct
     * @param $salsifyRecord
     */
    protected function _updateProductLinks($magentoProduct, $salsifyRecord)
    {
        $magentoProduct->setProductLinks([]); // clean links
        foreach (['related', 'upsell', 'crosssell'] as $type) {
            $this->_updateProductLinkType($magentoProduct, $salsifyRecord, $type);
        }
        try {
            $magentoProduct->getLinkInstance()->saveProductRelations($magentoProduct);
        } catch (\Exception $e) {
            $this->_logger->info("error trying to update product links " . $e->getMessage());
        }
    }

    /**
     * @param Product                        $magentoProduct
     * @param                                $salsifyRecord
     * @param                                $linkType
     *
     * @return mixed
     */
    protected function _updateProductLinkType($magentoProduct, $salsifyRecord, $linkType)
    {
        $productLinkFieldType = $this->_getFieldLinkType($linkType);
        if ($this->_getFieldLinkEnabled($linkType) && array_key_exists($productLinkFieldType, $salsifyRecord)) {
            $ucLinkType = ucwords($linkType);
            $this->_logger->info("Salsify {$ucLinkType} Field: " . json_encode($productLinkFieldType));
            $this->_logger->info("Salsify {$ucLinkType} Field Value: " . json_encode($salsifyRecord[$productLinkFieldType]));

            // Pull out SKU:
            $sku = $magentoProduct->getSku();
            $this->_logger->info("Magento Product Parent SKU: $sku");

            $relations = explode(',', $salsifyRecord[$productLinkFieldType]);
            $this->_logger->info("Salsify {$ucLinkType} Relation SKUs: " . json_encode($relations));
            if (count($relations) > 0) {
                $linkedData = [];
                $n = 1;
                foreach ($relations as $relation) {
                    if (!$this->_product->getIdBySku($relation)) {
                        $this->_logger->addNotice('--- MAGENTO SKU DOES NOT EXIST ---');
                        $this->_logger->addNotice("--- CANNOT LINK {$ucLinkType} RELATION SKU: " . $relation);
                        continue;
                    }
                    $relatedProductCollection = $this->_productLinkInterfaceFactory->create();
                    $this->_logger->info("Updating {$ucLinkType} Relation SKU: $relation");
                    $links = $relatedProductCollection
                        ->setSku($sku)
                        ->setLinkedProductSku($relation)
                        ->setLinkType($linkType)
                        ->setPosition($n);
                    $this->_logger->info("Linked {$ucLinkType} SKU #$n");
                    $n++;
                    $linkedData[] = $links;
                }
                $this->_logger->info("Total {$ucLinkType} Links Set: " . count($linkedData));

                $productLinks = $magentoProduct->getProductLinks();
                $productLinks = array_merge($productLinks, $linkedData);
                $magentoProduct->setProductLinks($productLinks);
            }

            return $magentoProduct;
        }

        return $magentoProduct;
    }

    /**
     * @param $type
     *
     * @return false|mixed
     */
    protected function _getFieldLinkEnabled($type)
    {
        $types = [
            'related'   => $this->_data->getProductsRelatedEnabled(),
            'upsell'    => $this->_data->getProductsUpsellEnabled(),
            'crosssell' => $this->_data->getProductsCrossellEnabled(),
        ];

        return array_key_exists($type, $types) ? $types[$type] : false;
    }

    /**
     * @param $type
     *
     * @return bool|mixed
     */
    protected function _getFieldLinkType($type)
    {
        $types = [
            'related'   => self::TRELLIS_SALSIFY_RELATED_SKUS,
            'upsell'    => self::TRELLIS_SALSIFY_UPSELL_SKUS,
            'crosssell' => self::TRELLIS_SALSIFY_CROSSSELL_SKUS,
        ];

        return array_key_exists($type, $types) ? $types[$type] : false;
    }

    /**
     * @param string[] $linkedProductsSku
     * @param          $groupedProduct
     * @param string   $linkType
     */
    protected function _addLinksToProduct($linkedProductsSku, $groupedProduct, $linkType = 'associated')
    {
        $this->_productLinks->setProductLinks($groupedProduct, $linkedProductsSku, $linkType);
    }

    /** CONFIGURABLE PRODUCTS **/

    /**
     * @param $salsifyAttributes
     * @param $products
     *
     * @return array
     */
    protected function _getConfigurableMapping(array $products)
    {
        $configurableMapping = [];
        if (!$this->_data->getConfigurableEnabled()) {
            $this->_logger->info("--- CONFIGURABLE PRODUCT MAPPING CONFIGURATION DISABLED ---");
            return $configurableMapping;
        }

        $this->_logger->info("--- BEGIN CONFIGURABLE PRODUCT MAPPING ---");
        foreach ($products as $product) {
            // Skip over configurable products or simples with no parent
            if ($product[self::TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE] === Configurable::TYPE_CODE || !isset($product[self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE])) {
                continue;
            }

            $parentIds = explode(',', $product[self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE]);

            foreach ($parentIds as $parentId) {
                if (!isset($configurableMapping[$parentId])) {
                    $configurableMapping[$parentId] = [];
                }
                $configurableMapping[$parentId][] = $product[self::TRELLIS_SALSIFY_ID_ATTRIBUTE];
                $this->_logger->info("Mapping simple product {$product[ self::TRELLIS_SALSIFY_ID_ATTRIBUTE ]} to parent {$parentId}");
            }
        }

        return $configurableMapping;
    }

    /**
     * @param array $salsifyProductsById
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _updateConfigurables(array $salsifyProductsById)
    {
        $configurableMapping = $this->_getConfigurableMapping($salsifyProductsById);
        if (empty($configurableMapping)) {
            return;
        }

        $this->_logger->info("--- BEGIN ASSIGNING SIMPLE PRODUCTS TO CONFIGURABLES ---");
        $this->_logger->info("Using Salsify property " . self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE . " to map simple to configurable products.");
        foreach ($configurableMapping as $parentSalsifyId => $childrenSalsifyIds) {
            if (!isset($salsifyProductsById[$parentSalsifyId][self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE])) {
                $this->_logger->info("The Salsify property " . self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE . " is not set.");
                continue;
            }

            // Get the parent product and make it a configurable
            /** @var $parentProduct Product */
            try {
                $parentProduct = $this->_getProductBySalsifyId($parentSalsifyId);
            } catch (\Exception $exception) {
                $this->_logger->error($exception->getMessage());
            }

            if (!$parentProduct) {
                return;
            }

            // Have to reset visibility here
            $parentSalsifyProduct = $salsifyProductsById[$parentProduct->getSku()];
            $parentProduct->setVisibility($parentSalsifyProduct['visibility']);
            $parentProduct->setTypeId(Configurable::TYPE_CODE);

            $this->_logger->info("Setting parent product {$parentSalsifyId} ({$parentProduct->getSku()}) as configurable.");

            // Get the child product IDs using trellis_salsify_id
            $childrenMagentoIds = $this->_getProductIdsFromSalsifyIds($childrenSalsifyIds);

            // If multiple attributes, split them
            $attributesFieldValue = $salsifyProductsById[$parentSalsifyId][self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE];
            $attributeCodes = explode(',', $attributesFieldValue);
            $this->_logger->info($attributesFieldValue);
            $this->_logger->info(print_r($attributeCodes, true));
            $this->_logger->info("Using Magento Attribute(s): {$attributesFieldValue} to map simple to configurable products.");

            // Load all child products from database first so we're not querying them in a nested loop
            $childProducts = [];
            foreach ($childrenMagentoIds as $childMagentoId) {
                $childProducts[$childMagentoId] = $this->_productRepository->getById($childMagentoId);
            }

            try {
                $existingConfigurableAttributes = $parentProduct->getTypeInstance()->getConfigurableAttributes($parentProduct);
                $existingSimpleProducts = $parentProduct->getTypeInstance()->getUsedProducts($parentProduct);
            } catch (\Exception $exception) {
                $this->_logger->error($exception->getMessage());
            }

            foreach ($existingSimpleProducts as $simpleProduct) {
                $childrenMagentoIds[$simpleProduct->getId()] = $simpleProduct->getId();
            }

            // Construct configurable attributes data based on simple products
            // @see https://github.com/magento/magento2/blob/2.1/dev/tests/integration/testsuite/Magento/ConfigurableProduct/_files/product_configurable.php
            $configurableAttributesData = [];
            $this->_logger->info("Begin constructing configurable attributes data.");
            $i = '0'; // ?
            foreach ($attributeCodes as $attributeCode) {
                $this->_logger->info("Constructing configurable attributes data for attribute {$attributeCode}");

                try {
                    $attribute = $this->_attributeRepository->get('catalog_product', $attributeCode);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    // Attribute does not exist
                    $this->_logger->info("Attribute {$attributeCode} does not exist: {$e->getMessage()}");
                    $attribute = null;
                }

                if ($attribute) {
                    // Build an array of values for this attribute for all simple products
                    $attributeValues = [];
                    foreach ($childProducts as $childMagentoId => $childProduct) {
                        $this->_logger->info("Getting value of attribute '{$attributeCode}' for child product sku {$childProduct->getSku()}.");

                        $optionId = $childProduct->getData($attributeCode);

                        $possibleValues = $childProduct->getResource()->getAttribute($attributeCode)->getSource()->getAllOptions();
                        // Find the matching label of the attribute for this product
                        foreach ($possibleValues as $possibleValue) {
                            if ($possibleValue['value'] == $optionId) {
                                $attributeValueLabel = $possibleValue['label'];
                                $this->_logger->info("Child product sku {$childProduct->getSku()} attribute '{$attributeCode}' has option_id '{$optionId}' and value '{$attributeValueLabel}'");
                                break;
                            }
                        }

                        if ((string) $attributeValueLabel == null) {
                            $this->_logger->info("ERROR: Cannot assign child product sku {$childProduct->getSku()} to parent product sku {$parentProduct->getSku()}! No option for attribute '{$attributeCode}' with option_id '{$optionId}' exists!");
                        } else {
                            if (!isset($attributeValues[$optionId])) {
                                // If this value has already been added to the array of all values, skip it
                                $attributeValues[$optionId] = [
                                    'label'        => $attributeValueLabel,
                                    'attribute_id' => $attribute->getId(),
                                    'value_index'  => $optionId,
                                ];
                            }
                        }
                    }

                    // Construct the data for this specific attribute
                    $configurableAttributesData[] = [
                        'attribute_id' => $attribute->getId(),
                        'code'         => $attribute->getAttributeCode(),
                        'label'        => $attribute->getStoreLabel(),
                        'position'     => $i,
                        'values'       => array_values($attributeValues),
                    ];
                    $i++;
                }
            }
            if (isset($existingConfigurableAttributes)) {
                foreach ($existingConfigurableAttributes as $existingConfigurableAttribute) {
                    foreach ($configurableAttributesData as $i => $data) {
                        $dataCopy = $data;
                        if ($data['attribute_id'] == $existingConfigurableAttribute->getAttributeId()) {
                            $optionsByValueIndex = [];
                            foreach ($existingConfigurableAttribute->getOptions() as $option) {
                                $optionsByValueIndex[$option['value_index']] = $option;
                            }

                            $valuesByValueIndex = [];
                            foreach ($data['values'] as $value) {
                                $valuesByValueIndex[$value['value_index']] = $value;
                            }

                            foreach ($optionsByValueIndex as $valueIndex => $option) {
                                if (!isset($valuesByValueIndex[$valueIndex])) {
                                    $dataCopy['values'][] = [
                                        'label'        => $option['label'],
                                        'attribute_id' => $data['attribute_id'],
                                        'value_index'  => $valueIndex,
                                    ];
                                }
                            }
                        }

                        $configurableAttributesData[$i] = $dataCopy;
                    }
                }
            }

            $configurableOptions = $this->_optionsFactory->create($configurableAttributesData);

            $this->_logger->info("Assigning configurable attribute options for parent product sku {$parentProduct->getSku()}.");
            $extensionConfigurableAttributes = $parentProduct->getExtensionAttributes();
            $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
            $extensionConfigurableAttributes->setConfigurableProductLinks($childrenMagentoIds);
            try {
                $this->_productRepository->save($parentProduct);
                $this->_logger->info("Successfully saved parent product sku {$parentProduct->getSku()}.");
            } catch (\Exception $e) {
                $this->_logger->addError($e->getMessage());
                $this->_logger->info("UNSUCCESSFULLY saved parent product sku {$parentProduct->getSku()}.");
            }
        }

        if (count($configurableMapping) > 0) {
            $this->_logger->info("--- ASSIGNING SIMPLE PRODUCTS TO CONFIGURABLES COMPLETE ---");
        }
    }

    /** BUNDLED PRODUCTS **/

    /**
     * @param ProductInterface $magentoProduct
     * @param                  $bundleProductsFieldArray
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _createBundledProduct(&$magentoProduct, $bundleProductsFieldArray)
    {
        $this->_logger->info(__METHOD__);
        $sku = $magentoProduct->getSku();
        $this->_logger->info("Parent SKU: $sku");
        $bundledSku = $sku . '_bundle';
        $bundledOptions = $this->_prepareBundleOptionsData($bundleProductsFieldArray);
        $bundledSelections = $this->_prepareBundleSelectionsData($bundleProductsFieldArray);
        /** @var ProductInterface $bundleProduct */
        $bundleProduct = $this->_productFactory->create();
        //\Magento\Bundle\Model\Product\Type::TYPE_CODE
        $bundleProduct->setTypeId('bundle')
            ->setAttributeSetId($magentoProduct->getAttributeSetId())
            ->setName($magentoProduct->getName())
            ->setSku($bundledSku)
            ->setUrlKey($bundledSku)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
            ->setPriceView(1)
            ->setPriceType(1)
            ->setPrice($magentoProduct->getPrice())
            ->setBundleOptionsData($bundledOptions)
            ->setBundleSelectionsData($bundledSelections);

        $this->_processBundleData($bundleProduct);
        try {
            $bundleProduct->save();
        } catch (\Exception $e) {
            $this->_logger->addError($e->getMessage());
        }
    }

    /**
     * @param $bundleProductsFieldArray
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _prepareBundleOptionsData($bundleProductsFieldArray)
    {
        $array = [];
        foreach ($bundleProductsFieldArray as $sku) {
            $id = $this->_product->getIdBySku($sku);
            $productName = $this->_productRepository->getById($id)->getName();
            $map = [
                'title'         => $productName,
                'default_title' => $productName,
                'type'          => 'select',
                'required'      => 1,
                'delete'        => '',
            ];

            $array[] = $map;
        }

        return $array;
    }

    /**
     * @param $bundleProductsFieldArray
     *
     * @return array
     */
    protected function _prepareBundleSelectionsData($bundleProductsFieldArray)
    {
        $array = [];

        foreach ($bundleProductsFieldArray as $sku) {
            $this->_logger->info("bundleProductsfieldArray SKU: " . json_encode($sku));
            $id = $this->_product->getIdBySku($sku);
            $this->_logger->info("Product ID: " . json_encode($id));
            $map = [
                [
                    'product_id'               => $id,
                    'selection_qty'            => 1,
                    'selection_can_change_qty' => 1,
                    'delete'                   => '',
                    'user_defined'             => 1
                ]
            ];
            $this->_logger->info("map: " . json_encode($map));

            $array[] = $map;

            //todo check product status enabled/disabled
        }
        $this->_logger->info("Array: " . json_encode($array));

        return $array;
    }

    /**
     * @param ProductInterface $bundleProduct
     * @param string[]         $bundleProductsFieldArray
     */
    protected function _updateBundleOptions($bundleProduct, $bundleProductsFieldArray)
    {
        try {
            $bundledOptions = $this->_prepareBundleOptionsData($bundleProductsFieldArray);
        } catch (NoSuchEntityException $e) {
            $this->_logger->addError($e->getMessage());
        }
        $bundledSelections = $this->_prepareBundleSelectionsData($bundleProductsFieldArray);
        $bundleProduct->setBundleOptionsData($bundledOptions)
            ->setBundleSelectionsData($bundledSelections);
        $this->_processBundleData($bundleProduct);
        try {
            $bundleProduct->save();
        } catch (\Exception $e) {
            $this->_logger->addError($e->getMessage());
        }
        // Todo: try to return something: true/false or product
        //return $bundleProduct;
    }

    /**
     * @param $bundleProduct
     */
    protected function _processBundleData($bundleProduct)
    {
        if ($bundleProduct->getBundleOptionsData()) {
            $options = [];
            foreach ($bundleProduct->getBundleOptionsData() as $key => $optionData) {
                if (!(bool) $optionData['delete']) {
                    /** @var \Magento\Bundle\Api\Data\OptionInterfaceFactory $optionInterface */
                    $option = $this->_optionInterface->create(['data' => $optionData]);
                    $option->setSku($bundleProduct->getSku());
                    $option->setOptionId(null);

                    $links = [];
                    $bundleLinks = $bundleProduct->getBundleSelectionsData();
                    $this->_logger->info("bundleLinks: " . json_encode($bundleLinks) . "count: " . count($bundleLinks));
                    if (!empty($bundleLinks[$key])) {
                        $this->_logger->info("bundleLinks[key]: " . json_encode($bundleLinks[$key]));
                        foreach ($bundleLinks[$key] as $linkData) {
                            $this->_logger->info("linkData: " . json_encode($linkData));
                            if (!(bool) $linkData['delete']) {
                                /** @var \Magento\Bundle\Api\Data\LinkInterface $link */
                                $link = $this->_linkInterfaceFactory->create(['data' => $linkData]);
                                try {
                                    $linkProduct = $this->_productRepository->getById($linkData['product_id']);
                                } catch (NoSuchEntityException $exception) {
                                    $this->_logger->error($exception->getMessage());
                                }
                                $link->setSku($linkProduct->getSku());
                                $this->_logger->info("SETTING LINK SKU: " . $linkProduct->getSku());
                                $link->setQty($linkData['selection_qty']);
                                if (isset($linkData['selection_can_change_qty'])) {
                                    $link->setCanChangeQuantity($linkData['selection_can_change_qty']);
                                }
                                $links[] = $link;
                            }
                        }
                        $option->setProductLinks($links);
                        $options[] = $option;
                    }
                }
            }
            $extension = $bundleProduct->getExtensionAttributes();
            $extension->setBundleProductOptions($options);
            $bundleProduct->setExtensionAttributes($extension);
        }
    }

    /**
     * @param $magentoProduct
     * @param $salsifyRecord
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _updateBundledProducts(&$magentoProduct, $salsifyRecord)
    {
        if ($this->_data->getBundledProductsEnabled() && array_key_exists(self::TRELLIS_SALSIFY_BUNDLED_SKUS, $salsifyRecord)) {
            $this->_logger->info("bundleProductsField: " . self::TRELLIS_SALSIFY_BUNDLED_SKUS);
            $this->_logger->info("salsifyBundleProductsField: " . $salsifyRecord[self::TRELLIS_SALSIFY_BUNDLED_SKUS]);

            $bundleProductsFieldArray = explode(',', $salsifyRecord[self::TRELLIS_SALSIFY_BUNDLED_SKUS]);
            array_unshift($bundleProductsFieldArray, $magentoProduct->getSku());

            $sku = $magentoProduct->getSku();
            $this->_logger->info("Parent SKU: $sku");
            $bundledSku = $sku . '_bundle';

            try {
                $product = $this->_productRepository->get($bundledSku);
                $this->_updateBundleOptions($product, $bundleProductsFieldArray);
            } catch (NoSuchEntityException $e) {
                $this->_logger->info("BUNDLE PRODUCT DOES NOT EXIST!");
                $this->_createBundledProduct($magentoProduct, $bundleProductsFieldArray);
            }

            $this->_logger->info("bundledProductsArray Value: " . json_encode($bundleProductsFieldArray));

            return $magentoProduct;
        }

        return $magentoProduct;
    }

    /** GROUPED PRODUCTS **/

    /**
     * @param $magentoProduct
     * @param $linkedProducts
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _createGroupedProduct(&$magentoProduct, $linkedProducts)
    {
        $this->_logger->info(__METHOD__);
        $sku = $magentoProduct->getSku();
        $this->_logger->info("Parent SKU: $sku");
        $groupedSku = $sku . '_grouped';
        $groupedProduct = $this->_productFactory->create();
        $groupedProduct->setTypeId(\Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE)
            //            ->setId(1)
            //            ->setWebsiteIds([1])
            ->setAttributeSetId($magentoProduct->getAttributeSetId())
            ->setName($magentoProduct->getName())
            ->setSku($groupedSku)
            ->setUrlKey($groupedSku)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1]);

        $this->_addLinksToProduct($linkedProducts, $groupedProduct);
        $this->_productRepository->save($groupedProduct);
    }

    /**
     * @param $magentoProduct
     * @param $salsifyRecord
     *
     * @return mixed
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _updateGroupedProducts(&$magentoProduct, $salsifyRecord)
    {
        if (array_key_exists(self::TRELLIS_SALSIFY_GROUPED_SKUS, $salsifyRecord) && $this->_data->getGroupedProductsEnabled()) {
            $this->_logger->info('groupedProductField: ' . self::TRELLIS_SALSIFY_GROUPED_SKUS);
            $this->_logger->info('groupedProductField Value: ' . $salsifyRecord[self::TRELLIS_SALSIFY_GROUPED_SKUS]);

            $groupedProductsFieldArray = explode(',', $salsifyRecord[self::TRELLIS_SALSIFY_GROUPED_SKUS]);
            array_unshift($groupedProductsFieldArray, $magentoProduct->getSku());

            $sku = $magentoProduct->getSku();
            $this->_logger->info("Parent SKU: $sku");
            $groupedSku = $sku . '_grouped';

            try {
                $product = $this->_productRepository->get($groupedSku);
                $this->_addLinksToProduct($groupedProductsFieldArray, $product);
            } catch (NoSuchEntityException $e) {
                $this->_logger->info('GROUPED PRODUCT DOES NOT EXIST!');
                $this->_createGroupedProduct($magentoProduct, $groupedProductsFieldArray);
            }

            $this->_logger->info("groupedProductsFieldArray Value: " . json_encode($groupedProductsFieldArray));

            return $magentoProduct;
        }

        return $magentoProduct;
    }

    /** CATEGORIES **/

    /**
     * @param Product $magentoProduct
     * @param array   $categoryIds
     *
     * @return mixed
     */
    protected function _updateCategories(&$magentoProduct, array $categoryIds)
    {
        if ($this->_data->getCategoryEnabled()) {
            try {
                $this->_categoryLinkManagement->assignProductToCategories($magentoProduct->getSku(), $categoryIds);

                $this->_logger->info("Assigning product {$magentoProduct->getSku()} to categories " . implode(',', $categoryIds));
            } catch (\Exception $e) {
                $this->_logger->addCritical('--- ERROR UPDATING CATEGORY ---');
                $this->_logger->addCritical("--- PRODUCT SKU: {$magentoProduct->getSku()}");
                $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
                $this->_logger->addCritical("--- MESSAGE: {$e->getMessage()}");
            }
        }

        return $magentoProduct;
    }

    /** MEDIA **/

    /**
     * @param $product
     * @param $salsifyAssetCollection
     * @param array $tags
     * @param string $process
     *
     * @return mixed
     * @throws CouldNotSaveException
     * @throws FileSystemException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _processMediaGalleryEntries($product, $salsifyAssetCollection, $tags = [], $process = null)
    {
        if (!is_array($salsifyAssetCollection)) {
            return $product;
        }

        // Grab the already-existing media gallery entries:
        // Have to re-load the product from the db because the images aren't stored in cache

        $mediaGalleryListForSku = $product->getMediaGalleryEntries();
        if (!is_array($mediaGalleryListForSku)) {
            $mediaGalleryListForSku = [];
        }

        // Create array of existing media gallery images for product, keyed on the hash of the file contents
        $mediaGalleryHashes = array_reduce($mediaGalleryListForSku, function ($accumulator, $asset) {
            try {
                $pathInfo = pathinfo($asset->getFile());
                $globString = "{$this->_directoryList->getPath('media')}/catalog/product{$pathInfo['dirname']}/{$pathInfo['filename']}*";
                $actualLocalFile = $this->_glob->glob($globString);
                $localFilePath = $actualLocalFile[0];
            } catch (FileSystemException $exception) {
                $this->_logger->error($exception->getMessage());
            }
            $accumulator[md5_file($localFilePath)] = $asset;

            return $accumulator;
        }, []);

        // Create array of salsify images for the product, keyed on the hash of the remote file contents
        $salsifyAssets = array_reduce(
            $salsifyAssetCollection,
            function ($collection, $asset) use ($mediaGalleryHashes, $product, $tags) {
                if (isset($asset[self::SALSIFY_URL_KEY])) {
                    $url = $asset[self::SALSIFY_URL_KEY];
                } else {
                    $url = $asset['url'];
                }

                $tags = isset($asset['tags']) ? (array) $asset['tags'] : $tags;

                $fileHash = $asset['hash'] ?? md5_file($url);

                // Don't overwrite position if the image was already set as part of the media gallery
                $position = $collection[$fileHash]['position'] ?? ($asset['position'] ?? null);

                $label = $asset['label'] ?? '';

                $collection[$fileHash] = [
                    "hash"     => $fileHash,
                    "url"      => $url,
                    "tags"     => $tags,
                    "position" => $position,
                    "label"    => $label,
                    "guid"     => isset($asset[self::SALSIFY_ID_KEY]) ? $asset[self::SALSIFY_ID_KEY] : '',
                    "filename" => isset($asset[self::SALSIFY_FILE_NAME]) ? $asset[self::SALSIFY_FILE_NAME] : pathinfo(
                        $url,
                        PATHINFO_FILENAME
                    ),
                ];

                // Update the image position and image tags if needed.
                if (isset($mediaGalleryHashes[$fileHash])) {
                    /** @var $mediaGalleryEntry \Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface */
                    $mediaGalleryEntry = $mediaGalleryHashes[$fileHash];
                    $mediaGalleryEntry->setPosition($position);
                    $mediaGalleryEntry->setLabel($label);

                    $oldTypes = $mediaGalleryEntry->getTypes();
                    sort($tags);
                    sort($oldTypes);

                    if ($oldTypes !== $tags) {
                        $mediaGalleryEntry->setTypes($tags);
                    }
                }
                return $collection;
            },
            []
        );

        // Reset the media gallery here to preserve tags
        // We'll handle deleting/adding new images later on
        if (count($mediaGalleryHashes)) {
            $product->setMediaGalleryEntries($mediaGalleryHashes);
            $product->setOptionsSaved(true);
            $this->_productRepository->save($product);
        }

        $imagesToUpload = array_diff_key($salsifyAssets, $mediaGalleryHashes);
        $imagesToDelete = array_diff_key($mediaGalleryHashes, $salsifyAssets);

        // Delete any images which have been removed in Salsify first
        $countDelete = count($imagesToDelete);
        if ($countDelete > 0) {
            //$this->_logger->info("Deleting {$countDelete} images from product sku {$product->getSku()}.");
            // Media Gallery Management does this, I think.  To be verified.
            //foreach ($imagesToDelete as $imageToDelete) {
                //$this->_logger->info("Deleting {$imageToDelete->getFile()} from product sku {$product->getSku()}.");
                //$this->_mediaGalleryManagement->remove($product->getSku(), $imageToDelete->getId());
            //}
        }

        // Upload any new images from Salsify
        $countUpload = count($imagesToUpload);
        if ($countUpload > 0) {
            $this->_logger->info("Uploading {$countUpload} {$process} images to product sku {$product->getSku()}.");
        }

        if (!$countDelete && !$countUpload) {
            $this->_logger->info("No {$process} images to delete or upload for product sku {$product->getSku()}.");
        }

        foreach ($imagesToUpload as $hash => $imageToUpload) {
            $filePath = $imageToUpload['url'];
            $tags = isset($imageToUpload['tags']) ? $imageToUpload['tags'] : [];
            $this->_logger->info("Salsify file url: {$filePath}");

            // Only load PNG and JPG for now:
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!in_array($extension, ["png", "jpg", "jpeg"])) {
                $this->_logger->info("Extension not supported: {$extension}");
                continue;
            }

            $this->_logger->info("File Extension: {$extension}");

            $fileName = $this->_isFilenameValid($imageToUpload['filename']);

            $this->_logger->info("Filename Valid: {$fileName}");

            $remoteFileName = basename($imageToUpload['url']);
            $downloadedImagePath = "{$this->_directoryList->getPath('media')}/import/{$remoteFileName}";
            if (!file_exists($downloadedImagePath)) {
                $this->_logger->info("Downloading file from Salsify: {$fileName} from url {$imageToUpload['url']}");
                $downloadedImagePath = $this->_downloadManager->downloadImage($imageToUpload['url']);

                if (empty($downloadedImagePath)) {
                    $this->_logger->info("Error trying to get file: {$filePath}");
                    continue;
                }
            } else {
                $this->_logger->info("Image already downloaded, skipping download {$imageToUpload['url']}");
            }

            $this->_logger->info("Adding {$process} image {$fileName} to product sku {$product->getSku()} using tags " . implode(',', $tags));
            $product->addImageToMediaGallery($downloadedImagePath, $tags, false, false);

            $this->_downloadedImages[$downloadedImagePath] = $downloadedImagePath;

            if (isset($imageToUpload['label'])) {
                $productMediaGallery = $product->getMediaGalleryImages();
                foreach ($productMediaGallery as $image) {
                    if ($fileName === pathinfo($image['file'], PATHINFO_FILENAME)) {
                        $this->_mediaGalleryProcessor->updateImage($product, $image['file'], ['label' => $imageToUpload['label']]);
                    }
                }
            }
        }
        try {
            $this->_productRepository->save($product);
        } catch (\Exception $e) {
            $this->_logger->error('--- ERROR SAVING PRODUCT MEDIA ---');
            $this->_logger->error('--- PRODUCT SKU: ' . $product->getSku());
            $this->_logger->error('--- METHOD: ' . __METHOD__);
            $this->_logger->error('--- MESSAGE: ' . json_encode($e->getMessage()));
        }
        return $product;
    }

    /**
     * Check if given filename is valid
     *
     * @param string $name
     *
     * @return bool
     */
    protected function _isFilenameValid($fileName)
    {
        // Cannot contain \ / ? * : " ; < > ( ) | { } \\
        if (!preg_match('/^[^\\/?*:";<>()|{}\\\\]+$/', $fileName)) {
            $split = preg_split('/[^\\/?*:";<>()|{}\\\\]+$/', $fileName);
            $fileName = substr($split[0], 0, -1);
        }

        return $fileName;
    }

    /** ATTRIBUTES **/

    /**
     * @param Product                        $product
     * @param                                $salsifyRecord
     *
     * @return Product
     * @throws \Exception
     */
    protected function _updateProductAttributeSetId(Product $magentoProduct, $salsifyRecord)
    {
        if ($this->_data->getAttributeSetEnabled()) {
            $salsifyRecordAttributeSetCode = $salsifyRecord[self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE];

            $this->_logger->info("attributeSetField Value: {$salsifyRecordAttributeSetCode}");

            $attributeSetCollection = $this->_attributeSetCollectionFactory->create();
            $attributeSets = $attributeSetCollection->addFieldToFilter('entity_type_id', 4)
                ->addFieldToFilter('attribute_set_name', $salsifyRecordAttributeSetCode)
                ->getItems();

            if (count($attributeSets)) {
                $this->_logger->info("attributeSets (array): " . json_encode($attributeSets));
                foreach ($attributeSets as $attributeSet) {
                    $this->_logger->info("attributeSets: {$attributeSet->getAttributeSetName()}");
                    $this->_logger->info("attributeSets: {$attributeSet->getAttributeSetId()}");
                    if (strtolower($attributeSet->getAttributeSetName()) !== strtolower($salsifyRecordAttributeSetCode)) {
                        $this->_logger->info("NO MATCH, CARRY ON.");
                        continue;
                    } else {
                        $this->_logger->info("MATCH! {$attributeSet->getAttributeSetId()} => {$salsifyRecordAttributeSetCode}");
                        $magentoProduct->setAttributeSetId($attributeSet->getAttributeSetId());
                        break;
                    }
                }
            } else {
                throw new \Exception("No attribute set exists with code {$salsifyRecordAttributeSetCode}");
            }
        } else {
            // If attribute set import is not enabled, then use the default
            $magentoProduct->setAttributeSetId(self::MAGENTO_DEFAULT_ATTR_SET_FOR_CATALOG_PRODUCTS);
        }

        return $magentoProduct;
    }

    /**
     * Get all Magento attribute frontend types.
     *
     * @return array
     */
    protected function _getMagentoAttributeFrontendTypes()
    {
        if ($this->_magentoAttributeFrontendTypes === null) {
            $searchCriteria = $this->_searchCriteriaBuilder->create();
            $attributeRepository = $this->_attributeRepository->getList(
                'catalog_product',
                $searchCriteria
            );

            // Grab the frontend input type, so we know how to format incoming information from Salsify:
            $this->_magentoAttributeFrontendTypes = [];
            foreach ($attributeRepository->getItems() as $attribute) {
                $this->_magentoAttributeFrontendTypes[$attribute->getAttributeCode()] = $attribute->getFrontendInput();
            }
        }

        return $this->_magentoAttributeFrontendTypes;
    }

    /**
     * @param $salsifyId
     * @param $salsifyProductsById
     *
     * @return array
     */
    protected function _getMergedAttributes($salsifyId, $salsifyProductsById)
    {
        $attributes = $salsifyProductsById[$salsifyId];
        if (isset($attributes[self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE], $salsifyProductsById[$attributes[self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE]])) {
            $parentId = $attributes[self::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE];
            unset($salsifyProductsById[$parentId]['url_key']);
            $attributes = array_merge($attributes, array_diff_key($salsifyProductsById[$parentId], $attributes));

            if (isset($attributes[self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE])) {
                unset($attributes[self::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE]);
            }
        }

        return $attributes;
    }

    /**
     * If the Salsify record contains attributes which have a special type in Magento (i.e. select, multiselect, date, etc.)
     * Then calculate the value which Magento needs to input into the system.
     *
     * Example:
     *  Attribute `color` is a 'select' attribute in Magento. It is returned as a string from Salsify.
     *  Maps color 'Black' to option ID '4'.
     *
     * @param array $salsifyRecord
     * @param array $attributeMapping
     * @param array $matchingFields
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _mapSpecialAttributeTypes($salsifyRecord)
    {
        $magentoAttributes = [];
        $attributeFrontendTypes = $this->_getMagentoAttributeFrontendTypes();

        $hardcodeConstantValue = $this->_hardcodeConstantValueExists($salsifyRecord);

        foreach ($salsifyRecord as $matchingField => $value) {
            // Don't map it if we're supposed to ignore it
            if ($value !== $hardcodeConstantValue) {
                $attributeFrontendType = isset($attributeFrontendTypes[$matchingField]) ? $attributeFrontendTypes[$matchingField] : null;
                switch ($attributeFrontendType) {
                    case "multiselect":
                        // Do some special stuff for multiselect magento attributes
                        if (is_array($value)) {
                            $multiselectValuesFromSalsify = $value;
                        } else {
                            // Split by either semicolon or comma
                            // If neither present, force to array
                            if (strpos($value, ';') !== false) {
                                $multiselectValuesFromSalsify = explode(';', $value);
                            } elseif (strpos($value, ',') !== false) {
                                $multiselectValuesFromSalsify = explode(',', $value);
                            } else {
                                $multiselectValuesFromSalsify = (array) $value;
                            }
                        }

                        $multiselectValuesInMagento = array_map(function ($splitValue) use ($matchingField) {
                            return $this->_productHelper->createOrGetOptionId($matchingField, $splitValue);
                        }, $multiselectValuesFromSalsify);
                        $multiselectValuesInMagento = implode(',', $multiselectValuesInMagento);
                        $magentoAttributes[$matchingField] = $multiselectValuesInMagento;
                        break;
                    case "select":
                        // Do some special stuff for select magento attributes

                        $attribute = $this->_eavConfig->getAttribute('catalog_product', $matchingField);

                        $swatchValue = null;

                        if ($this->_swatch->isSwatchAttribute($attribute)) {
                            if ($this->_swatch->isTextSwatch($matchingField)) {
                                //$re = '/(?<label>[\w\s\-\_]+)([,\s]+)?(?<value>[\w\s\-\_]+)?$/i';
                                $re = '/^(?<label>.*)(?:\,)(?<value>.*)$/i';
                            } else {
                                $re = '/^(?<label>.*)(?:\,)(?<value>.*)$/im';
                            }
                            if (preg_match($re, $value, $matches)) {
                                $value = isset($matches['label']) ? trim($matches['label']) : $value;
                                $swatchValue = isset($matches['value']) ? trim($matches['value']) : '';
                            }
                            $this->_logger->debug("Regex -- value: $value ; swatchvalue $swatchValue");
                        }

                        if ($attribute && ($source = $attribute->getSourceModel()) && !stripos($source, 'table')) {
                            $options = $attribute->getSource()->getAllOptions();
                            foreach ($options as $key) {
                                if (!is_array($key)) {
                                    if ($key['label'] === $value) {
                                        $magentoAttributes[$matchingField] = [$key['value']];
                                        break;
                                    }
                                } else {
                                    if ($key['value'] === $value || (string) $key['label'] === $value) {
                                        $magentoAttributes[$matchingField] = $key['value'];
                                        break;
                                    }
                                }
                            }
                        } else {
                            $magentoAttributes[$matchingField] = $this->_productHelper->createOrGetOptionId($matchingField, $value);
                        }

                        if ($swatchValue) {
                            $this->_logger->info("saveSwatch: $matchingField, {$magentoAttributes[$matchingField]}, $swatchValue");
                            $this->_swatch->saveSwatch($matchingField, $magentoAttributes[$matchingField], $swatchValue);
                        }
                        break;
                    case "date":
                        // Reformat the date to a standardized format
                        $format = 'Y-m-d';
                        $d = \DateTime::createFromFormat($format, $value);
                        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
                        if ($d && $d->format($format) === $value) {
                            $magentoAttributes[$matchingField] = $value;
                        } else {
                            $this->_logger->info("Invalid date value \"$value\" expected format: \"$format\"");
                        }
                        break;
                    case 'gallery':
                        if (!is_array($value)) {
                            $value = explode(',', trim($value, ','));
                        }
                        $magentoAttributes[$matchingField] = $value;
                        break;
                    case 'boolean':
                        // convert (yes | no) to (1 | 0)
                        $magentoAttributes[$matchingField] = (preg_match('/yes|true|(?<!-)[1-9]/i', $value)) ? 1 : 0;
                        break;
                    default:
                        if (is_array($value)) {
                            $magentoAttributes[$matchingField] = '';
                            foreach ($value as $salsifyProperty => $salsifyPropertyValue) {
                                $magentoAttributes[$matchingField] .= $salsifyProperty .": ". $salsifyPropertyValue . PHP_EOL;
                            }
                        } else {
                            // All other cases just use the salsify value
                            $magentoAttributes[$matchingField] = $value;
                        }
                        break;
                }
            }
        }
        // Reset constant value, if $hardcodeConstantValue exists
        if ($hardcodeConstantValue) {
            $magentoAttributes[self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE] = $salsifyRecord[self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE];
        }

        return $magentoAttributes;
    }

    protected function _hardcodeConstantValueExists($record)
    {
        if (!array_key_exists(self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE, $record)) {
            return null;
        }
        return $record[self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE];
    }

    /**
     * @param Product                        $product
     * @param                                $attributes
     */
    protected function _updateProductAttributes(Product $product, $attributes)
    {
        // Don't set url_key if it's already been set
        if ($product->getUrlKey()) {
            unset($attributes['url_key']);
        }

        $hardcodeConstantValue = $attributes[self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE] ?? null;
        if ($hardcodeConstantValue) {
            foreach ($attributes as $property => $value) {
                if ($value === $hardcodeConstantValue) {
                    unset($attributes[$property]);
                }
            }
        }

        $subscriptionIntervalsGrid = $attributes[self::PARADOXLABS_SUBSCRIPTION_INTERVALS_GRID] ?? null;
        if ($subscriptionIntervalsGrid && $this->_isJsonValid($subscriptionIntervalsGrid)) {
            $subscriptionIntervalsGrid = json_decode($subscriptionIntervalsGrid, true);
            $intervals = $subscriptionIntervalsGrid[self::PARADOXLABS_SUBSCRIPTION_INTERVALS_KEY];
            $product->setData(self::PARADOXLABS_SUBSCRIPTION_INTERVALS_GRID, $intervals);
            $product = $this->_productRepository->save($product);
            unset($attributes[self::PARADOXLABS_SUBSCRIPTION_INTERVALS_GRID]);
        }

        $product->addData($attributes);

        // Unset media gallery, we'll handle this in _updateProductMediaGallery
        $product->unsetData(self::TRELLIS_SALSIFY_MEDIA_GALLERY_ATTRIBUTE);

        if (!$product->getPrice()) {
            $product->setPrice(0.00);
        }

        try {
            $this->_productRepository->cleanCache();
            $product->setOptionsSaved(true);
            $product = $this->_productRepository->save($product);
            $this->_createdProducts[$product->getSku()] = $attributes;
        } catch (\Exception $e) {
            $this->_failedProducts[$product->getSku()] = $e->getMessage();
            $this->_logger->addCritical('--- ERROR SAVING PRODUCT ---');
            $this->_logger->addCritical("--- PRODUCT SKU: {$product->getSku()}");
            $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
            $this->_logger->addCritical('--- MESSAGE: ' . json_encode($e->getMessage()));
            //$this->_logger->addCritical('--- START STACK TRACE ---');
            //$this->_logger->addError($e->getTraceAsString());
            //$this->_logger->addCritical('--- END STACK TRACE ---');
            // code needs to go here to increment and recall this function if the error msg = "URL key for specified store already exists."
        }

        return $product;
    }

    /** OTHER PRODUCT METHODS */

    /**
     * @param $salsifyProductsById
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function _getProductsToUpdate($salsifyProductsById)
    {
        $salsifyIds = array_keys($salsifyProductsById);
        $salsifySkus = [];
        foreach ($salsifyProductsById as $salsifyProduct) {
            $salsifySkus[] = $salsifyProduct['sku'];
        }

        // First get products by trellis_salsify_id
        $attributes = [
            ['attribute' => self::TRELLIS_SALSIFY_ID_ATTRIBUTE, 'in' => $salsifyIds],
        ];

        /** @var $magentoProductsBySalsifyId Collection */
        $magentoProductsBySalsifyId = $this->_productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter($attributes);

        $attributes = [
            ['attribute' => 'sku', 'in' => $salsifySkus],
        ];

        /** @var $magentoProductsBySku Collection */
        $magentoProductsBySku = $this->_productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter($attributes);

        foreach ($magentoProductsBySku as $product) {
            if (!$magentoProductsBySalsifyId->getItemById($product->getId())) {
                try {
                    $magentoProductsBySalsifyId->addItem($product);
                } catch (LocalizedException $e) {
                    $this->_logger->error($e->getMessage());
                }
            }
        }

        return $magentoProductsBySalsifyId;
    }

    /**
     * Update product links (related, crosssells, upsells), bundled products, and grouped products.
     * This must be run after products are created
     *
     * @param array $salsifyProductsById
     *
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _updateProductRelations(array $salsifyProductsById)
    {
        foreach (['related', 'upsell', 'crosssell'] as $linkType) {
            $enabledLinks[] = $this->_getFieldLinkEnabled($linkType);
        }
        // sigh. do we really need to run through every product before we find out these features aren't enabled?
        if (
            (!empty(array_filter($enabledLinks))) ||
            $this->_data->getBundledProductsEnabled() ||
            $this->_data->getGroupedProductsEnabled()
        ) {
            foreach ($salsifyProductsById as $salsifyId => $salsifyProduct) {
                try {
                    $magentoProduct = $this->_productRepository->get($salsifyProduct['sku']);
                    $this->_updateProductLinks($magentoProduct, $salsifyProduct);
                    $this->_updateBundledProducts($magentoProduct, $salsifyProduct);
                    $this->_updateGroupedProducts($magentoProduct, $salsifyProduct);
                } catch (NoSuchEntityException $exception) {
                    $this->_logger->error($exception->getMessage());
                    $this->_logger->addCritical("--- PRODUCT SKU: {$salsifyProduct['sku']}");
                    $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
                } catch (\Exception $exception) {
                    $this->_logger->error($exception->getMessage());
                    $this->_logger->addCritical("--- PRODUCT SKU: {$salsifyProduct['sku']}");
                    $this->_logger->addCritical('--- METHOD: ' . __METHOD__);
                }
            }
        }
    }

    /**
     * @param array $salsifyProductsById
     */
    protected function _updateAttributeSets(array $salsifyProductsById)
    {
        if ($this->_data->getUpdateAttributeSetsDuringSync()) {
            $attributeSetsMapping = [];
            foreach ($salsifyProductsById as $salsifyProduct) {
                if (isset($salsifyProduct[self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE])) {
                    $attributeSetName = $salsifyProduct[self::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE];

                    if (!isset($attributeSetsMapping[$attributeSetName])) {
                        $attributeSetsMapping[$attributeSetName] = [];

                        $attributeSetCollection = $this->_attributeSetCollectionFactory->create();
                        $attributeSet = $attributeSetCollection->addFieldToFilter('entity_type_id', 4)
                            ->addFieldToFilter('attribute_set_name', $attributeSetName)
                            ->getItems();

                        if (count($attributeSet)) {
                            $this->_logger->info("Attribute set {$attributeSetName} already exists, skipping create.");
                        } else {
                            $this->_productAttributes->createAttributeSet($attributeSetName);
                        }
                    }

                    $hardcodeConstantValue = $salsifyProduct[self::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE];
                    // Don't add any of the hardcoded trellis salsify properties to any attribute set
                    foreach (self::TRELLIS_SALSIFY_ATTRIBUTES as $trellisSalsifyAttributeCode) {
                        unset($salsifyProduct[$trellisSalsifyAttributeCode]);
                    }

                    foreach ($salsifyProduct as $property => $value) {
                        if ($value === $hardcodeConstantValue) {
                            unset($salsifyProduct[$property]);
                        }
                    }

                    $attributeSetsMapping[$attributeSetName] = array_merge($attributeSetsMapping[$attributeSetName], $salsifyProduct);
                }
            }

            foreach ($attributeSetsMapping as $attributeSetName => $attributes) {
                $this->_productAttributes->addAttributesToSet(
                    array_keys($attributes),
                    $attributeSetName,
                    ProductAttributes::ATTRIBUTE_SET_GROUP_NAME
                );
            }
        }
    }

    /**
     * @param false $manual
     */
    public function execute($manual = false): void
    {
        if ($manual) {
            $this->updateViaManual();
        } elseif (!$this->rabbitmq->isEnabled()) {
            $this->updateViaApi();
        } else {
            try {
                $this->pushSalsifyProductsToQueue->execute($this->_client->getProductFeed());
            } catch (IntegrationException $e) {
                $this->_logger->info($e->getMessage());
            }
        }
    }

    // ABSTRACT METHODS

    abstract public function updateViaApi();

    abstract public function updateViaManual();

    abstract public function upsertProducts($salsifyProductsById);

    abstract protected function _updateProductMediaGallery(Product $product, $salsifyRecord);

    abstract protected function _createSalsifyAttributes();
}
