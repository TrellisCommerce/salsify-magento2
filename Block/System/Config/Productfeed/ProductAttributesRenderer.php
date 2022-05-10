<?php
/**
 * @author    travis @ Trellis
 * @copyright Copyright (c) Trellis.co (https://trellis.co/)
 * @package   evercare
 */

namespace Trellis\Salsify\Block\System\Config\Productfeed;

use Trellis\Salsify\Helper\Product;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;


/** @method setName(string $value)
 */
class ProductAttributesRenderer extends Select
{
    /**
     * @var Product
     */
    private $productHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Product $productHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Product $productHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->productHelper = $productHelper;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->productHelper->getAttributes());
        }
        return parent::_toHtml();
    }

    /**
     * Sets name for input element
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

}