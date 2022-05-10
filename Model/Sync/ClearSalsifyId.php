<?php


namespace Trellis\Salsify\Model\Sync;


use Magento\Bundle\Api\Data\LinkInterfaceFactory;
use Magento\Bundle\Api\Data\OptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;
use Magento\Downloadable\Api\Data\SampleInterfaceFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Helper\Product;
use Trellis\Salsify\Model\ClientFactory;
use Trellis\Salsify\Model\MediaDownloadManager;
use Trellis\Salsify\Model\Product\Gallery\Video\Processor;
use Trellis\Salsify\Model\Sync;
use Magento\Framework\Notification\NotifierInterface as NotifierPool;
use Magento\Framework\Stdlib\DateTime\DateTime;


class ClearSalsifyId extends Sync
{
    
    public function updateViaApi()
    {
        $this->updateAll();
    }

    public function updateViaManual()
    {
        $this->updateAll();
    }

    protected function updateAll()
    {
        $stores = $this->_storeManagementInterface->getStores(true);
        $websites = $this->_storeManagementInterface->getWebsites(true);
        $collection = $this->_productCollectionFactory->create();
        $this->_logger->info('Clearing trellis_salsify_id started');
        /** @var Product $product */
        foreach ($collection as $product) {
            try {
                $this->_logger->info('Clearing trellis_salsify_id for: '. $product->getSku());

                foreach($websites as $website) {
                    $product->setWebsiteId($website->getId());
                    $this->saveNullProductAttrs($product);
                }

                foreach($stores as $store) {
                    $product->setStoreId($store->getId());
                    $this->saveNullProductAttrs($product);
                }

            } catch (LocalizedException $e) {
                $this->_logger->info('trellis_salsify_id error: '. $e->getMessage());
            }
        }
        $this->_logger->info('Clearing trellis_salsify_id finished');
    }

    /**
     * @param Product $product
     * @throws \Exception
     */
    private function saveNullProductAttrs($product)
    {
        $product->setTrellisSalsifyId(null);
        $product->setTrellisSalsifyLastUpdated(null);
        $this->productResource->saveAttribute($product, 'trellis_salsify_id');
        $this->productResource->saveAttribute($product, 'trellis_salsify_last_updated');
    }

    public function upsertProducts($salsifyProductsById)
    {
    }

    protected function _updateProductMediaGallery(\Magento\Catalog\Model\Product $product, $salsifyRecord)
    {
    }

    protected function _createSalsifyAttributes()
    {
    }
}