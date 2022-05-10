<?php

/**
 * Copyright 2020 (c) Trellis, All rights reserved.
 */

declare(strict_types=1);

namespace Trellis\Salsify\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractExtensibleModel;
use Trellis\Salsify\Api\Data\SalsifyProductInterface;
use Trellis\Salsify\Api\Data\SalsifyRecordInterface;
use Magento\Framework\Model\Context;
use Magento\Catalog\Model\ResourceModel\AbstractResource;

/**
 * SalsifyRecord class
 */
class SalsifyRecord extends AbstractExtensibleModel implements SalsifyRecordInterface
{

    /** @var ProductFactory $productFactory */
    private $productFactory;

    /** @var ProductResource $productResource */
    private $productResource;

    /** @var ProductRepositoryInterface $productRepository */
    private $productRepository;

    /**
     * SalsifyRecord constructor.
     *
     * @param Context                                            $context
     * @param \Magento\Framework\Registry                        $registry
     * @param ExtensionAttributesFactory                         $extensionFactory
     * @param AttributeValueFactory                              $customAttributeFactory
     * @param AbstractResource|null                              $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param ProductRepositoryInterface                         $productRepository
     * @param ProductFactory                                     $productFactory
     * @param ProductResource                                    $productResource
     * @param array                                              $data
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        ProductResource $productResource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource, $resourceCollection, $data);
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productResource = $productResource;
    }

    /**
     * @inheritDoc
     */
    public function getTargetProductId()
    {
        return $this->getData(self::TARGET_PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function getProductId()
    {
        return $this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function getProductSystemId()
    {
        return $this->getData(self::PRODUCT_SYSTEM_ID);
    }

    /**
     * @inheritDoc
     */
    public function getParentId()
    {
        return $this->getData(self::PARENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function getParentSystemId()
    {
        return $this->getData(self::PARENT_SYSTEM_ID);
    }

    /**
     * @inheritdoc
     */
    public function getProduct()
    {
        return $this->getData(self::PRODUCT);
    }

    /**
     * @inheritdoc
     */
    public function setTargetProductId($targetProductId)
    {
        return $this->setData(self::TARGET_PRODUCT_ID, $targetProductId);
    }

    /**
     * @inheritDoc
     */
    public function setProductId($productId)
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function setProductSystemId($productSystemId)
    {
        return $this->setData(self::PRODUCT_SYSTEM_ID, $productSystemId);
    }

    /**
     * @inheritDoc
     */
    public function setParentId($parentId)
    {
        return $this->setData(self::PARENT_ID, $parentId);
    }

    /**
     * @inheritDoc
     */
    public function setParentSystemId($parentSystemId)
    {
        return $this->setData(self::PARENT_SYSTEM_ID, $parentSystemId);
    }

    /**
     * @inheritDoc
     */
    public function setProduct($product)
    {
        return $this->setData(self::PRODUCT, $product);
    }

    /**
     * @return Product
     */
    public function getProductForSave()
    {
        $productToSave = $this->productFactory->create();
        if ($this->getEntityId()) {
            $this->productResource->load($productToSave, $this->getEntityId());
        }
        return $productToSave;
    }

}


