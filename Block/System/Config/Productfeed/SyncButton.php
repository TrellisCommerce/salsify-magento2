<?php

namespace Trellis\Salsify\Block\System\Config\Productfeed;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class Button
 * @package Trellis\Salsify\Block\System\Config
 */
class SyncButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Trellis_Salsify::system/config/syncButton.phtml';

    protected $_buttonId = 'product_feed_sync_btn';

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
        return $this->getUrl('salsify/system_config_productfeed/sync'); // controller url
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => $this->getButtonId(),
                'label' => __('Salsify Sync'),
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