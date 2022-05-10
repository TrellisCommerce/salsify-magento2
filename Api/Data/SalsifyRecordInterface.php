<?php
/**
 * Copyright 2020 (c) Trellis, All rights reserved.
 */

declare(strict_types=1);

namespace Trellis\Salsify\Api\Data;

use Trellis\Salsify\Model\Record\SalsifyProduct;
use Trellis\Salsify\Model\SalsifyRecordFactory;

/**
 * DataObject for Salsify Item
 */
interface SalsifyRecordInterface
{
    public const TARGET_PRODUCT_ID = 'target_product_id';
    public const PRODUCT_ID = 'product_id';
    public const PRODUCT_SYSTEM_ID = 'product_system_id';
    public const PARENT_ID = 'parent_id';
    public const PARENT_SYSTEM_ID = 'parent_system_id';
    public const PRODUCT = 'product';

    /**
     * @return string
     */
    public function getTargetProductId();

    /**
     * @return string
     */
    public function getProductId();

    /**
     * @return string
     */
    public function getProductSystemId();

    /**
     * @return string
     */
    public function getParentId();

    /**
     * @return string
     */
    public function getParentSystemId();

    /**
     * @return SalsifyProductInterface
     */
    public function getProduct();

    /**
     * @param string $targetProductId
     *
     * @return $this
     */
    public function setTargetProductId($targetProductId);

    /**
     * @param string $productId
     *
     * @return $this
     */
    public function setProductId($productId);

    /**
     * @param string $productSystemId
     *
     * @return $this
     */
    public function setProductSystemId($productSystemId);

    /**
     * @param string $parentId
     *
     * @return $this
     */
    public function setParentId($parentId);

    /**
     * @param string $parentSystemId
     *
     * @return $this
     */
    public function setParentSystemId($parentSystemId);

    /**
     * @param SalsifyProductInterface $product
     *
     * @return $this
     */
    public function setProduct($product);

}



