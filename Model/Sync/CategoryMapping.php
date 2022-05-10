<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

namespace Trellis\Salsify\Model\Sync;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor;
use Trellis\Salsify\Helper\Data as DbConfig;

class CategoryMapping
{
    const MAGENTO_CATEGORY_DELIMITER = '/';

    const CATEGORY_BASE_INDEX = 0;

    /**
     * @var CategoryProcessor
     */
    private $categoryProcessor;
    /**
     * @var DbConfig
     */
    private $dbConfig;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var string
     */
    private $defaultCategoryName;

    /**
     * Category constructor.
     * @param CategoryProcessor $categoryProcessor
     * @param DbConfig $dbConfig
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        CategoryProcessor $categoryProcessor,
        DbConfig $dbConfig,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->categoryProcessor = $categoryProcessor;
        $this->dbConfig = $dbConfig;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param $categoryData
     * @return array
     */
    public function processCategories($categoryData)
    {
        if (!is_array($categoryData)) {
            $categoryData = [$categoryData];
        }
        $values = $this->processValues($categoryData);
        $magentoValues = str_replace($this->getArrayDelimiter(), self::MAGENTO_CATEGORY_DELIMITER, $values);
        $magentoValues = explode(self::MAGENTO_CATEGORY_DELIMITER, $magentoValues);
        $magentoValues = implode(self::MAGENTO_CATEGORY_DELIMITER, array_map('trim', $magentoValues));
        return $this->categoryProcessor->upsertCategories($magentoValues, ',');
    }

    /**
     * @param $categoryData
     * @return string
     */
    private function processValues($categoryData)
    {
        $categories = [];
        foreach ($categoryData as $categoryRow) {

            // append default root category
            if (strpos($categoryRow, $this->getDefaultCategoryName()) === false) {
                $categoryRow = $this->getDefaultCategoryName() . $this->getArrayDelimiter() . $categoryRow;
            }

            //FIXME: it only works properly when the come is at the deeper(last) level
            $commaExploded = explode(',', $categoryRow);
            if (sizeof($commaExploded) === 1) {
                $categories[] = $categoryRow;
                continue;
            }
            $base = $commaExploded[self::CATEGORY_BASE_INDEX];
            $slashExploded = explode($this->getArrayDelimiter(), $base);
            array_pop($slashExploded);
            $root = implode($this->getArrayDelimiter(), $slashExploded) . $this->getArrayDelimiter();
            $children = explode(',', str_replace($root, '', $categoryRow));
            $categories = array_merge($categories, array_map(function($row) use ($root) {
                return implode('', [$root, $row]);
            }, $children));

        }
        return implode(',', $categories);
    }

    /**
     * @return string
     */
    private function getArrayDelimiter()
    {
        return $this->dbConfig->getCategoryStringDelimiter();
    }

    /**
     * @return string
     */
    private function getDefaultCategoryName()
    {
        if (!$this->defaultCategoryName) {
            try {
                $category = $this->categoryRepository->get($this->dbConfig->getRootNodeId());
                $this->defaultCategoryName = $category->getName();
            } catch (\Exception $e) {
                $this->defaultCategoryName = "";
            }
        }
        return $this->defaultCategoryName;
    }

}
