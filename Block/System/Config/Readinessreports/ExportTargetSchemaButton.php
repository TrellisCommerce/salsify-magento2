<?php

namespace Trellis\Salsify\Block\System\Config\Readinessreports;

use \Trellis\Salsify\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Trellis\Salsify\Helper\ReadinessReports;
use Trellis\Salsify\Model\TargetSchema;

/**
 * Class DebugButton
 * @package Trellis\Salsify\Block\System\Config
 */
class ExportTargetSchemaButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Trellis_Salsify::system/config/readinessreports/exportTargetSchemaButton.phtml';

    protected $_htmlId = 'export_target_schema';

    protected $_dataHelper;
    protected $_readinessReportsHelper;

    /**
     * @param Context $context
     * @param array   $data
     */
    public function __construct(
        Data $dataHelper,
        ReadinessReports $readinessReportsHelper,
        Context $context,
        array $data = []
    ) {
        $this->_dataHelper             = $dataHelper;
        $this->_readinessReportsHelper = $readinessReportsHelper;

        parent::__construct($context, $data);
    }

    public function getHtmlId()
    {
        return $this->_htmlId;
    }

    public function getButtonId()
    {
        return "{$this->getHtmlId()}_btn";
    }

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     *
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
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function downloadTargetSchemaFile()
    {
        return $this->getUrl('salsify/download/getfile', [TargetSchema::FILENAME]);
    }

    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('salsify/system_config_readinessreports/exportTargetSchema'); // controller url
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
                'id'    => $this->getButtonId(),
                'label' => __('Export Target Schema'),
            ]
        );

        return $button->toHtml();
    }

    /**
     * Are there any attributes to export set
     *
     * @return bool
     */
    public function hasExportAttributes()
    {
        return !empty($this->_readinessReportsHelper->getTargetSchemaAttributes());
    }
}