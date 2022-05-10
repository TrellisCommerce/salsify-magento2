<?php

namespace Trellis\Salsify\Helper;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Model\Entity\Attribute\OptionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Model\Entity\Attribute\Source\TableFactory;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Dir\Reader;
use Trellis\Salsify\Logger\Logger;

/**
 * Class Product
 * @package Trellis\Salsify\Helper
 */
class Product extends AbstractHelper
{

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var AttributeRepository
     */
    protected $_attributeRepository;
    /**
     * @var AttributeOptionManagementInterface
     */
    protected $_attributeOptionManagement;
    /**
     * @var AttributeOptionLabelInterfaceFactory
     */
    protected $_attributeOptionLabelFactory;
    /**
     * @var OptionFactory
     */
    protected $_optionFactory;
    /**
     * @var array
     */
    protected $_attributes = [];

    /**
     * @var array
     */
    protected $_productAttributes = [];

    /**
     * @var
     */
    protected $_attributeValues;
    /**
     * @var Reader
     */
    protected $_moduleDirReader;
    /**
     * @var TableFactory
     */
    protected $_tableFactory;
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Data constructor.
     *
     * ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param AttributeRepository $attributeRepository
     * @param AttributeOptionManagementInterface $attributeOptionManagement
     * @param AttributeOptionLabelInterfaceFactory $attributeOptionLabelFactory
     * @param OptionFactory $optionFactory
     * @param Context $context
     * @param Reader $moduleDirReader
     * @param TableFactory $tableFactory
     * @param Logger $logger
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AttributeRepository $attributeRepository,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionLabelInterfaceFactory $attributeOptionLabelFactory,
        OptionFactory $optionFactory,
        Context $context,
        Reader $moduleDirReader,
        TableFactory $tableFactory,
        Logger $logger
    ) {
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_attributeRepository = $attributeRepository;
        $this->_attributeOptionManagement = $attributeOptionManagement;
        $this->_attributeOptionLabelFactory = $attributeOptionLabelFactory;
        $this->_optionFactory = $optionFactory;
        $this->_moduleDirReader = $moduleDirReader;
        $this->_tableFactory = $tableFactory;
        $this->logger = $logger;

        parent::__construct($context);
    }

    /**
     * Get attribute by code.
     *
     * @param string $attributeCode
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface|bool
     */
    public function getAttribute($attributeCode)
    {
        if (!isset($this->_attributes[$attributeCode])) {
            try {
                $this->_attributes[$attributeCode] = $this->_attributeRepository->get('catalog_product', $attributeCode);
            } catch (NoSuchEntityException $e) {
                $this->_attributes[$attributeCode] = false;
            }
        }
        return $this->_attributes[$attributeCode];
    }

    public function getAttributes()
    {
        if (!$this->_productAttributes) {
            $searchCriteria = $this->_searchCriteriaBuilder->create();

            // We default to filtering out the system required non visible attributes
            $filterSystemAttributes = new Filter();
            $filterSystemAttributes->setField('attribute_code')
                ->setValue($this->getSystemRequiredNonVisibleAttributes())
                ->setConditionType('nin');

            $filterGroup = new FilterGroup();
            $filterGroup->setFilters([$filterSystemAttributes]);

            $searchCriteria->setFilterGroups([$filterGroup]);

            $attributeRepository = $this->_attributeRepository->getList(
                'catalog_product',
                $searchCriteria
            );
            foreach ($attributeRepository->getItems() as $productAttribute) {
                $options[ $productAttribute->getAttributeId() ] = $productAttribute->getAttributeCode();
            }
        }
        return $options;
    }

    /**
     * Find or create a matching attribute option
     *
     * @param string $attributeCode Attribute the option should exist in
     * @param string $label Label to find or add
     * @return int
     * @throws LocalizedException
     */
    public function createOrGetOptionId($attributeCode, $label)
    {
        $this->logger->info("createOrGetOptionId(" . json_encode($attributeCode) . ", " . json_encode($label) . ")");
        if (strlen($label) < 1) {
            throw new LocalizedException(
                __('Label for %1 must not be empty.', $attributeCode)
            );
        }

        // Does it already exist?
        $optionId = $this->getOptionId($attributeCode, $label);

        if (!$optionId) {
            // If not, add it
            $attribute = $this->getAttribute($attributeCode);

            $attributeOptionLabel = $this->_attributeOptionLabelFactory->create();
            $attributeOptionLabel->setStoreId(0)
                ->setLabel($label);
            $option = $this->_optionFactory->create();
            $option->setLabel($label)
                ->setStoreLabels([$attributeOptionLabel])
                ->setSortOrder(0)
                ->setIsDefault(false);
            $this->_attributeOptionManagement->add('catalog_product', $attribute->getAttributeId(), $option);

            // Get the inserted ID. Should be returned from the installer, but it isn't.
            $optionId = $this->getOptionId($attributeCode, $label, true);
        }

        return $optionId;
    }

    /**
     * Find the ID of an option matching $label, if any.
     *
     * @param string $attributeCode Attribute code
     * @param string $label Label to find
     * @param bool $force If true, will fetch the options even if they're already cached.
     * @return int|false
     */
    public function getOptionId($attributeCode, $label, $force = false)
    {
        /** @var Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        //This is how Magento pick the optionId on Magento_Eav::Model/Entity/Attribute/OptionManagement.php:68
        $optionId = $attribute->getSource()->getOptionId($label);
        if($optionId)
            return $optionId;
        //I'm not certain if the code bellow this is still needed

        // Build option array if necessary
        if ($force === true || !isset($this->_attributeValues[ $attribute->getAttributeId() ])) {
            $this->_attributeValues[ $attribute->getAttributeId() ] = [];

            // We have to generate a new sourceModel instance each time through to prevent it from
            // referencing its _options cache. No other way to get it to pick up newly-added values.

            /** @var Table $sourceModel */
            $sourceModel = $this->_tableFactory->create();
            $sourceModel->setAttribute($attribute);

            foreach ($sourceModel->getAllOptions() as $option) {
                $this->_attributeValues[ $attribute->getAttributeId() ][ $option['label'] ] = $option['value'];
            }
        }

        // Return option ID if exists
        if (isset($this->_attributeValues[ $attribute->getAttributeId() ][ $label ])) {
            return $this->_attributeValues[ $attribute->getAttributeId() ][ $label ];
        }

        // Return false if does not exist
        return false;
    }

    /**
     * These are all system attributes which we want to exclude everywhere.
     * There are other system attributes (name, price, sku) which we always want to show.
     *
     * @return array
     */
    public function getSystemRequiredNonVisibleAttributes()
    {
        return [
            'sku_type',
            'weight_type',
            'price_view',
            'shipment_type',
            'price_type',
        ];
    }
}
