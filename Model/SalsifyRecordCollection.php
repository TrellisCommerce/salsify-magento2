<?php

/**
 * Copyright 2020 (c) Trellis, All rights reserved.
 */

declare(strict_types=1);

namespace Trellis\Salsify\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Api\AbstractServiceCollection;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Trellis\Salsify\Api\Data\SalsifyRecordInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;

class SalsifyRecordCollection extends AbstractServiceCollection
{

    private $productRepository;

    private $productResource;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ProductRepositoryInterface $productRepository,
ProductResource $productResource,
        array $data = []
    ) {
        parent::__construct($entityFactory, $filterBuilder, $searchCriteriaBuilder, $sortOrderBuilder);
        $this->productRepository = $productRepository;
        $this->productResource = $productResource;
    }

    public function getProcessorMap()
    {

    }

    public function processItem($item, $processor)
    {

    }

    public function samplePreprocess($item)
    {

    }



    /**
     * @param DataObject $item
     *
     * @return SalsifyRecordCollection
     * @throws \Exception
     */
    public function addItem($item)
    {
        if ($product = $item->getProduct()) {
            if ($product->hasSku()) {
                $item->setData('sku', $product->getSku());
                if ($id = $this->productResource->getIdBySku($item->getSku())) {
                    $item->setEntityId($id);
                } else {
                    $item->setEntityId(0);
                }
            }
        }


        /*
                    if ($item->getProduct()->hasProductType()) {
                        $item->setData('product_type', $item->getProduct()->getProductType());
                        if ($item->getProductType() === 'simple' && $item->getProduct()->hasParentId()) {
                            $item->setData('parent_sku', $item->getProduct()->getParentId());
                            if ($parentProduct = $this->productRepository->get($item->getParentSku())) {
                                $item->setData('parent', $parentProduct);
                                $item->setData('parent_entity_id', $parentProduct->getId());
                            }
                        }
                    }

                    if ($item->getProduct()->hasConfigurableVariationLabels()) {
                        $item->setData('used_configurable_attributes', explode(',', $item->getProduct()->getConfigurableVariationLabels()));
                    }
                    if ($currentProduct = $this->productRepository->get($item->getSku())) {
                        $item->setCurrentProduct($currentProduct);
                        $item->setEntityId($currentProduct->getId());
                        $item->setIsNew(0);
                    } else {
                        $item->setCurrentProduct(null);
                        $item->setEntityId(null);
                        $item->setIsNew(1);
                    }
                }*/

        return parent::addItem($item);

    }


}



