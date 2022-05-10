<?php

namespace Trellis\Salsify\Block\System\Config\Webhook;

use Magento\Framework\Data\Form\Element\AbstractElement;

class HookUrl extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
    }

    private function getHookUrl()
    {
        $url = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $url .= 'rest/default/V1/salsify/update';
        return $url;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->getHookUrl();
        $ret = '<input type="text" id="copy-url-input" value="'.$url.'" disabled/>';
        $element->setValue($ret);
        return $element->getValue();
    }

    /**
     * Render element value
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _renderValue(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<td class="value with-tooltip url-to-clipboard">';
        $html .= $this->_getElementHtml($element);
        $html .= '<div class="tooltip"><span class="help"><span></span></span>';
        $html .= '<div class="tooltip-content">' . $element->getTooltip() . '</div></div>';
        $html .= '<div class="clipboard"><span><a href=""></a></span><div class="clipboard-tooltip">Copy to clipboard</div></div>';

        $html .= <<< EOS
<script>
    require([
        'jquery',
        'prototype',
    ], function($){

        // Fill in Webhook URL when it's different than the generated one
        realInput = $('#trellis_salsify_webhook_url');

        if (realInput.val() !== $('#copy-url-input').val()) {
            realInput.val($('#copy-url-input').val());
        }

        $('.url-to-clipboard').click(function(event) {
            event.preventDefault();
            var inp = $('#copy-url-input');
            inp.select();
            var temp = $("<input>");
            $("body").append(temp);
            temp.val(inp.val()).select();
            document.execCommand("copy");
            temp.remove();
        });
    });
</script>

EOS;


        if ($element->getComment()) {
            $html .= '<p class="note"><span>' . $element->getComment() . '</span></p>';
        }
        $html .= '</td>';
        return $html;
    }
}
