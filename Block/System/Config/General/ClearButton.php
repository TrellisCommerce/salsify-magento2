<?php

namespace Trellis\Salsify\Block\System\Config\General;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class Button
 * @package Trellis\Salsify\Block\System\Config
 */
class ClearButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Trellis_Salsify::system/config/clearButton.phtml';

    protected $_buttonId = 'clear_salsify_id';

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('salsify/system_config_general/clear'); // controller url
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => $this->getButtonId(),
                'label' => __('Clear Salsify IDs'),
            ]
        );

        return $button->toHtml();
    }

    public function getButtonId()
    {
        return $this->_buttonId;
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}