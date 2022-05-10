<?php

namespace Trellis\Salsify\Block\System\Config\Productfeed;

use Magento\Framework\DataObject;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Exception\LocalizedException;


/**
 * Class ExportAttributesMultiSelect
 * @package Trellis\Salsify\Block\System\Config
 */
class AttributePropertyMapping extends AbstractFieldArray
{

    /**
     * @var ProductAttributesRenderer
     */
    protected $productAttributeRenderer;

    /**
     * Returns renderer for product attribute element
     * @return ProductAttributesRenderer
     * @throws LocalizedException
     */
    protected function getProductAttributeRenderer(): ProductAttributesRenderer
    {
        if (!$this->productAttributeRenderer) {
            $this->productAttributeRenderer = $this->getLayout()->createBlock(
                ProductAttributesRenderer::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->productAttributeRenderer;
    }

    /**
     * Prepare to render
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'property',
            [
                'label'     => __('Salsify Property'),
                'size' => '50px',
                'class' => 'required-entry'
            ]
        );
        $this->addColumn(
            'attribute',
            [
                'label' => __('Magento Attribute'),
                'renderer'  => $this->getProductAttributeRenderer(),
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Mapping');
    }

    /**
     * Prepare existing row data object
     * @param DataObject $row
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $productAttribute = $row->getData('attribute');
        $options = [];
        if ($productAttribute) {
            $options['option_' . $this->getProductAttributeRenderer()->calcOptionHash($productAttribute)]
                = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }
}