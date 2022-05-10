<?php

namespace Trellis\Salsify\Helper;

use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 *
 * @package Trellis\Salsify\Helper
 */
class Data extends AbstractHelper
{

    /**
     * @var string
     */
    public const PRODUCT_FEED_API_PATH = 'channels/:channel_id/runs/latest';

    public const XML_PATH_TRELLIS_SALSIFY_DEBUG_DEFAULT_CLEAR_DEBUG_LOG = 'trellis_salsify/debug/default_clear_debug_log';

    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    protected $mapping = [];

    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    protected $_date;

    private $composerInfo;

    /**
     * Data constructor.
     *
     * @param Context                                     $context
     * @param ModuleListInterface                         $moduleList
     * @param ProductMetadataInterface                    $productMetadata
     * @param SerializerInterface                         $serializer
     * @param AttributeRepositoryInterface                $attributeRepository
     * @param SearchCriteriaBuilder                       $searchCriteriaBuilder
     * @param DateTime $date
     * @param PackageInfo                                 $composerInfo
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata,
        SerializerInterface $serializer,
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DateTime $date,
        PackageInfo $composerInfo
    ) {
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->_serializer = $serializer;
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_date = $date;
        parent::__construct($context);
        $this->composerInfo = $composerInfo;
    }

    /** ===== GENERAL SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getStatus($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/general/status', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getDeleteEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/general/delete', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getIgnoreTimestampAndAlwaysUpdate($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            'trellis_salsify/general/ignore_timestamp_and_always_update',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getOrganizationId($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/general/organization_id', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getApiKey($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/general/api_key', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return int
     */
    public function getTimeout($store = null)
    {
        return (int) $this->scopeConfig->getValue('trellis_salsify/general/timeout', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return int
     */
    public function getVisibilityLevel($store = null)
    {
        return (int) $this->scopeConfig->getValue('trellis_salsify/general/visibility_level', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== WEBHOOK SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getWebhookEnabled($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/webhook/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getWebhookUrl($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/webhook/url', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== CONFIGURABLE SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getConfigurableEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/configurable/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== MEDIA & IMAGES SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getMediaGalleryEnabled($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/media/media_gallery_enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getMediaGalleryProperty($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/media/media_gallery_property', 'store', $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getImageMappingEnabled($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/media/image_mapping_enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getImageMapping($store = null)
    {
        $serializedImageMapping = $this->scopeConfig->getValue('trellis_salsify/media/image_mapping', ScopeInterface::SCOPE_STORE, $store);

        return json_decode($serializedImageMapping, true);
    }

    /**
     * @param null $store
     *
     * @return bool
     */
    public function getVideoEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/media/video_enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getVideoField($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/media/video_mapping', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== BUNDLED SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getBundledProductsEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/bundled/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== GROUPED SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getGroupedProductsEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/grouped/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== VIRTUAL SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getVirtualEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/virtual/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getVirtualField($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/virtual/attributes_field', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getVirtualDetailsField($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/virtual/details_field', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== DOWNLOADABLE SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getDownloadableEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/downloadable/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getDownloadableDetailsField($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/downloadable/details_field', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getDownloadableSampleField($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/downloadable/sample_field', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== CUSTOM OPTIONS SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getCustomOptionsEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/custom_options/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getCustomOptionsField($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/custom_options/attributes_field', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== PRODUCT RELATIONS SECTION ===== */

    /**
     * @param null $store
     *
     * @return bool
     */
    public function getProductsRelatedEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            'trellis_salsify/product_relations/products_related_enabled',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     *
     * @return bool
     */
    public function getProductsCrossellEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            'trellis_salsify/product_relations/products_crossell_enabled',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     *
     * @return bool
     */
    public function getProductsUpsellEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            'trellis_salsify/product_relations/products_upsell_enabled',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /** ===== ATTRIBUTE SET SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getAttributeSetEnabled($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/attribute_set/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getUpdateAttributeSetsDuringSync($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/attribute_set/update_attribute_sets', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getCreateAttributesDuringSync($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/attribute_set/create_attributes', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== CATEGORY SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getCategoryEnabled($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/category/category_enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getRootNodeId($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/category/root_node_id', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== WEBSITES SECTION ===== */

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getWebsiteEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag('trellis_salsify/websites/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    /** ===== DEBUGGING SECTION ===== */

    /**
     * @return PackageInfo
     */
    public function getPackageInfo()
    {
        return $this->composerInfo;
    }

    /**
     * @return mixed
     */
    public function getExtensionVersion()
    {
        $moduleCode = 'Trellis_Salsify';
        return $this->getPackageInfo()->getVersion($moduleCode);
    }

    /**
     * @return mixed
     */
    public function getSetupVersion()
    {
        $moduleCode = 'Trellis_Salsify';
        $moduleInfo = $this->moduleList->getOne($moduleCode);
        return $moduleInfo['setup_version'];
    }

    /**
     * @return mixed
     */
    public function getDefaultClearDebugLog($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_TRELLIS_SALSIFY_DEBUG_DEFAULT_CLEAR_DEBUG_LOG,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @return string
     */
    public function getMagentoEdition()
    {
        return $this->productMetadata->getEdition();
    }

    /**
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getCategoryType($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/category/category_type', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getCategoryStringDelimiter($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/category/category_string_delimiter', ScopeInterface::SCOPE_STORE, $store);
    }
}
