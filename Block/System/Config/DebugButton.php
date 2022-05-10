<?php

namespace Trellis\Salsify\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class DebugButton
 * @package Trellis\Salsify\Block\System\Config
 */
class DebugButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Trellis_Salsify::system/config/debugButton.phtml';

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
     * @param AbstractElement $element
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
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @param $fileName
     * @return string
     */
    public function downloadLogFiles($fileName)
    {
        return $this->getUrl('salsify/download/getfile', [$fileName]);
    }

    /**
     * Generate debug button html
     *
     * @return string
     * @throws LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id'        => 'debug_btn',
                'label'     => __('Download Log File'),
            ]
        );

        return $button->toHtml();
    }
}