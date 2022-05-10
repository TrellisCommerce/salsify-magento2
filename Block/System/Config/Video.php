<?php
/**
 * TRELLIS
 *
 * Date: 4/26/2019
 * Time: 8:56 AM
 *
 * @package Trellis Salsify
 * @author Travis Hill <travis@trellis.co>
 * @copyright 2019 Trellis (https://www.trellis.co)
 */


namespace Trellis\Salsify\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * Class Video
 * @package Trellis\Salsify\Block\System\Config
 */
class Video extends Field
{
    /**
     *
     */
    const XML_PATH_YOUTUBE_API = 'catalog/product_video/youtube_api_key';
    /**
     *
     */
    const XML_PATH_TRELLIS_VIDEO_ENABLED = 'trellis_salsify/media/video_enabled';

    /**
     * @var UrlInterface
     */
    protected $_backendUrl;
    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @var WriterInterface
     */
    protected $_writer;

    /**
     * @param UrlInterface $backendUrl
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $writer
     * @param Context $context
     */
    public function __construct(
        UrlInterface $backendUrl,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $writer,
        Context $context
    ) {
        $this->_backendUrl = $backendUrl;
        $this->_scopeConfig = $scopeConfig;
        $this->_writer = $writer;
        parent::__construct($context);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $field = $element->getData('name');

        if (strpos($field, 'video_enabled') !== false) {
            $field = 'video_enabled';
            $videoAPI = $this->_scopeConfig->getValue(self::XML_PATH_YOUTUBE_API);
            $backendUrl = $this->_backendUrl->getUrl('adminhtml/system_config/edit/section/catalog', ['group' => 'product_video']);
            if (!$videoAPI) {
                $this->_writer->save(
                    self::XML_PATH_TRELLIS_VIDEO_ENABLED,
                    '0',
                    $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    $scopeId = 0
                );
                return 'You must set your YouTube API Key to enable this functionality.</br><a href="'.$backendUrl.'">Configuration > Catalog [Catalog] > Product Video</a>'
                    . $this->buildHtml(true);
            }
            return $this->buildHtml();
        }
    }

    /**
     * @param bool $hidden
     * @return string
     */
    protected function buildHtml($hidden = false)
    {
        $selected = $this->_scopeConfig->getValue(self::XML_PATH_TRELLIS_VIDEO_ENABLED);
        $selectedHtml = ' selected="selected"';
        $html = '<select id="trellis_salsify_media_video_enabled" name="groups[media][fields][video_enabled][value]" class=" select admin__control-select"';
        $html .= ($hidden === true) ? ' style="display:none" ' : '';
        $html .= 'data-ui-id="select-groups-media-fields-video-enabled-value">';
        $html .= '<option value="1"';
        $html .= ($selected == 1) ? $selectedHtml : '';
        $html .= '>Yes</option>';
        $html .= '<option value="0"';
        $html .= ($selected == 0) ? $selectedHtml : '';
        $html .= '>No</option>';
        $html .= '</select>';
        return $html;
    }
}
