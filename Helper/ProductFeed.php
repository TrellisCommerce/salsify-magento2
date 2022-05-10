<?php

namespace Trellis\Salsify\Helper;

use Magento\Store\Model\ScopeInterface;
use Trellis\Salsify\Model\Sync\ProductAttributes;

/**
 * Class ProductFeed
 * @package Trellis\Salsify\Helper
 */
class ProductFeed extends Data
{
    public function getProductFeedEnabled($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/product_feed/enabled', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getChannelId($store = null)
    {
        return $this->scopeConfig->getValue('trellis_salsify/product_feed/channel_id', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getPropertyMapping($store = null)
    {
        // Only set mapping once
        if (empty($this->mapping)) {
            // Get mapping as defined in system config
            $serializedMapping = $this->scopeConfig->getValue(
                'trellis_salsify/product_feed/property_mapping',
                ScopeInterface::SCOPE_STORE,
                $store
            );
            $mapping = json_decode($serializedMapping, true);

            // Also get all attributes which have been created from an import
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(
                    'attribute_code',
                    str_replace('_', "\_", ProductAttributes::ATTRIBUTE_CODE_PREFIX) . "%",
                    'like'
                ) // _ is a special character in Mysql so we need to escape it
                ->create();

            $attributeRepository = $this->attributeRepository->getList(
                'catalog_product',
                $searchCriteria
            );

            foreach ($attributeRepository->getItems() as $attribute) {
                $mapping[str_replace(
                    ProductAttributes::ATTRIBUTE_CODE_PREFIX,
                    '',
                    $attribute->getAttributeCode()
                )] = $attribute->getAttributeCode();
            }
            $this->mapping = $mapping;
        }
        return $this->mapping;
    }
}
