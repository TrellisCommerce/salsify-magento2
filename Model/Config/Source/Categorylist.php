<?php

namespace Trellis\Salsify\Model\Config\Source;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Option\ArrayInterface;

/**
 * Class Categorylist
 * @package Trellis\Salsify\Model\Config\Source
 */
class Categorylist implements ArrayInterface
{
    /**
     * @var CategoryFactory
     */
    protected $_categoryFactory;
    /**
     * @var CollectionFactory
     */
    protected $_categoryCollectionFactory;

    /**
     * Categorylist constructor.
     * @param CategoryFactory $categoryFactory
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->_categoryFactory = $categoryFactory;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @param bool $isActive
     * @param bool $level
     * @param bool $sortBy
     * @param bool $pageSize
     * @return Collection
     * @throws LocalizedException
     */
    public function getCategoryCollection($isActive = true, $level = false, $sortBy = false, $pageSize = false)
    {
        $collection = $this->_categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        // select only active categories
        if ($isActive) {
            $collection->addIsActiveFilter();
        }

        // select categories of certain level
        if ($level) {
            $collection->addLevelFilter($level);
        }

        // sort categories by some value
        if ($sortBy) {
            $collection->addOrderField($sortBy);
        }

        // select certain number of categories
        if ($pageSize) {
            $collection->setPageSize($pageSize);
        }

        return $collection;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function toOptionArray()
    {
        $arr = $this->_toArray();
        $ret = [];

        foreach ($arr as $key => $value) {
            $ret[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        return $ret;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    private function _toArray()
    {
        $categories = $this->getCategoryCollection(true, false, false, false);

        $catagoryList = [];
        foreach ($categories as $category) {
            $catagoryList[$category->getEntityId()] = __($this->_getParentName($category->getPath()) . $category->getName());
        }

        return $catagoryList;
    }

    /**
     * @param string $path
     * @return string
     */
    private function _getParentName($path = '')
    {
        $parentName = '';
        $rootCats = [1,2];

        $catTree = explode("/", $path);
        // Deleting category itself
        array_pop($catTree);

        if ($catTree && (count($catTree) > count($rootCats))) {
            foreach ($catTree as $catId) {
                if (!in_array($catId, $rootCats)) {
                    $category = $this->_categoryFactory->create()->load($catId);
                    $categoryName = $category->getName() . '(' . $category->getId() . ')';
                    $parentName .= $categoryName . ' > ';
                }
            }
        }

        return $parentName;
    }
}
