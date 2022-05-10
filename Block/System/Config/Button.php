<?php

namespace Trellis\Salsify\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class Button
 * @package Trellis\Salsify\Block\System\Config
 */
class Button extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Trellis_Salsify::system/config/button.phtml';

    protected $_productFeedButtonId = 'product_feed_sync_btn';
    protected $_readinessReportButtonId = 'readiness_report_sync_btn';

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
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('salsify/system_config/button'); // controller url
    }

    /**
     * Generate button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProductFeedSyncButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => $this->getProductFeedSyncButtonId(),
                'label' => __('Salsify Sync (Product Feed)'),
            ]
        );

        return $button->toHtml();
    }

    /**
     * Generate button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getReadinessReportSyncButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => $this->getReadinessReportSyncButtonId(),
                'label' => __('Salsify Sync (Readiness Report)'),
            ]
        );

        return $button->toHtml();
    }

    public function getProductFeedSyncButtonId()
    {
        return $this->_productFeedButtonId;
    }

    public function getReadinessReportSyncButtonId()
    {
        return $this->_readinessReportButtonId;
    }
}