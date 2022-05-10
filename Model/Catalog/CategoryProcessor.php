<?php
/**
 * Copyright 2020 (c) Trellis, All rights reserved.
 */

declare(strict_types=1);

namespace Trellis\Salsify\Model\Catalog;

use Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor as Processor;

/**
 * class CategoryProcessor
 *
 * Process String of Categories Name Hierarchy, or category Comma-separated IDs
 *
 */
class CategoryProcessor
{

    public const CATEGORY_SEPARATOR = "|";

    /** @var Processor $processor */
    private $processor;

    /**
     * CategoryProcessor constructor.
     *
     * @param Processor $processor
     */
    public function __construct(
        Processor $processor
    ) {
        $this->processor = $processor;
    }

    /**
     * Checks for Categories as hierarchy names or ID(s) separated by comma.
     * Returns IDs as array, or looks up category hierarchy, creating new categories
     * and returning array of IDs
     *
     * Multiple IDs are separated by comma, or |
     * Multiple categories are separated by |
     *
     * Example 1:
     *
     * Category names:
     *      "Default Category/Clothing/Tshirts|Default Category/Clothing/Dress Shirts";
     *
     * Returns: the array of IDs for "Tshirts" and "Dress Shirts", but creates any categories
     *      that do not exist.
     *
     * Example 2:
     *
     * Category IDs:
     * "59,121,10,3,175,186"
     *
     * @param string $categoryData
     *
     * @return array
     */
    public function processCategories(string $categoryData): array
    {
        $categoryIds = [];
        $re = '/(?<!\w)\d+(?!\w)/';

        if (preg_match_all($re, $categoryData, $matches)) {
            $categoryIds = $matches[0];
        } else {
            if ($categoryData && str_contains($categoryData, '/')) {
                $categoryIds = $this->processor->upsertCategories($categoryData, self::CATEGORY_SEPARATOR);
            }
        }

        return $categoryIds;
    }
}
