<?php

namespace Trellis\Salsify\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\Form\Element\Factory;
use Trellis\Salsify\Helper\Product;


/**
 * Class ExportAttributesMultiSelect
 * @package Trellis\Salsify\Block\System\Config
 */
class ProductAttributes extends AbstractFieldArray
{
    /**
     * @var AttributeRepositoryInterface
     */
    protected $_attributeRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var Factory
     */
    protected $_elementFactory;

    /**
     * @var Product
     */
    protected $_productHelper;

    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Context $context,
        Factory $elementFactory,
        Product $productHelper,
        array $data = []
    ) {
        $this->_attributeRepository   = $attributeRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_elementFactory        = $elementFactory;
        $this->_productHelper         = $productHelper;
        parent::__construct($context, $data);
    }

    protected function _prepareToRender()
    {
        $this->addColumn('attribute',
                         ['label' => __('Attribute'), 'size' => '50px', 'class' => 'required-entry']);
        $this->_addAfter       = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    /**
     * @param string $columnName
     *
     * @return string
     */
    public function renderCellTemplate($columnName)
    {
        if ($columnName == 'attribute' && isset($this->_columns[ $columnName ])) {
            $searchCriteria = $this->_searchCriteriaBuilder->create();

            // We default to filtering out the system required non visible attributes
            $filterSystemAttributes = new Filter();
            $filterSystemAttributes->setField('attribute_code')
                ->setValue($this->_productHelper->getSystemRequiredNonVisibleAttributes())
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

            $element = $this->_elementFactory->create('select');
            $element->setForm(
                $this->getForm()
            )->setName(
                $this->_getCellInputElementName($columnName)
            )->setHtmlId(
                $this->_getCellInputElementId('<%- _id %>', $columnName)
            )->setValues(
                $options
            );

            return str_replace("\n", '', $element->getElementHtml());
        }

        return parent::renderCellTemplate($columnName);
    }
}