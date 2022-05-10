<?php

namespace Trellis\Salsify\Block\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Version renderer with link
 *
 * @category   Trellis
 * @package    Trellis_salsify
 * @author     Travis Hill <travis@trellis.co>
 * @website    http://www.trellis.co
 */
class Version extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string Extension URL
     */
    const EXTENSION_URL = 'http://www.trellis.co';
    /**
     * @var \Trellis\Salsify\Helper\Data $helper
     */
    protected $_helper;
    /**
     * @param   \Magento\Backend\Block\Template\Context $context
     * @param   \Trellis\Salsify\Helper\Data   $helper
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Trellis\Salsify\Helper\Data $helper
    ) {
        $this->_helper = $helper;
        parent::__construct($context);
    }
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $extensionVersion = $this->_helper->getExtensionVersion();
        $extensionTitle   = 'Salsify Connector';
        $versionLabel     = sprintf(
            '<a href="%s" title="%s" target="_blank">%s</a>',
            self::EXTENSION_URL,
            $extensionTitle,
            $extensionVersion
        );
        $element->setValue($versionLabel);
        return $element->getValue();
    }
}