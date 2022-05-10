<?php
/**
 * @author    travis @ Trellis
 * @copyright Copyright (c) Trellis.co (https://trellis.co/)
 * @package   evercare
 */

namespace Trellis\Salsify\Helper;

use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Trellis\Salsify\Helper\ProductFeed;

class Config extends Data
{
    /**
     * @var ProductFeed
     */
    protected $_productFeed;

    public function __construct(
        ProductFeed $productFeed,
        Context $context,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata,
        SerializerInterface $serializer,
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DateTime $date,
        PackageInfo $composerInfo
    ) {
        $this->_productFeed = $productFeed;
        parent::__construct($context, $moduleList, $productMetadata, $serializer, $attributeRepository,
            $searchCriteriaBuilder, $date, $composerInfo);
    }

    public function getEnabledConfiguration() {
        $config = [
            // General
            $generalEnabled = $this->getStatus() ? "[General] Enabled: " . $this->getStatus() : null,
            $generalAllowDelete = $this->getDeleteEnabled() ? "[General] Allow Delete: " . $this->getDeleteEnabled() : null,
            $generalIgnoreTimestampAndAlwaysUpdate = $this->getIgnoreTimestampAndAlwaysUpdate() ? "[General] Ignore Timestamp and Always Update: " . $this->getIgnoreTimestampAndAlwaysUpdate() : null,
            $generalOrganizationId = $this->getOrganizationId() ? "[General] Organization ID: " . $this->getOrganizationId() : null,
            // mixed thoughts on the api key being in the log files.
            //$generalApiKey = $this->getApiKey() ? "[General] API Key: " . $this->getApiKey() : null,
            $generalApiTimeout = $this->getTimeout() ? "[General] API Timeout: " . $this->getTimeout() : null,
            $generalDefaultNewProductVisibilityLevel = $this->getVisibilityLevel() ? "[General] Default New Product Visibility Level: " . $this->getVisibilityLevel() : null,

            // Product Feed
            $productFeedEnabled = $this->_productFeed->getProductFeedEnabled() ? "[Product Feed] Enabled: " . $this->_productFeed->getProductFeedEnabled() : null,
            $productFeedChannelId = $this->_productFeed->getChannelId() ? "[Product Feed] Channel ID: " . $this->_productFeed->getChannelId() : null,
            $productFeedPropertyMapping = $this->_productFeed->getPropertyMapping() ? "[Product Feed] Property Mapping: " . json_encode($this->_productFeed->getPropertyMapping()) : null,

            // Readiness Reports
            // Webhook
            // Configurable Products

            // Media & Images
            $mediaImagesMediaGalleryEnabled = $this->getMediaGalleryEnabled() ? "[Media & Images] Media Gallery Enabled: " . $this->getMediaGalleryEnabled() : null,
            $mediaImagesSalsifyMediaGalleryProperty = $this->getMediaGalleryProperty() ? "[Media & Images] Salsify Media Gallery Property: " . $this->getMediaGalleryProperty() : null,
            $mediaImagesImageMappingEnabled = $this->getImageMappingEnabled() ? "[Media & Images] Image Mapping Enabled: " . $this->getImageMappingEnabled() : null,
            $mediaImagesImageTagMapping = $this->getImageMapping() ? "[Media & Images] Image Tag Mapping: " . json_encode($this->getImageMapping()) : null,

            // Attribute Set Settings
            // Bundled Products
            // Grouped Products
            // Virtual Products
            // Downloadable Products
            // Websites
            // Custom Options
            // Product Relation Settings
            // Category Import Settings
            // RabbitMQ

            // Debug
            $debugDefaultClearDebugLog = $this->getDefaultClearDebugLog() ? "[Debugging] Clear Log After Successful Sync: " . $this->getDefaultClearDebugLog() : null
        ];
        return array_filter($config);
    }
}