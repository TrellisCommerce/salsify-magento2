<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Service;

use Magento\Framework\Exception\LocalizedException;
use Trellis\Salsify\Api\WebHookUpdateInterface;
use Trellis\Salsify\Controller\Hook\Update;
use Trellis\Salsify\Helper\Data;

class WebHookUpdate implements WebHookUpdateInterface
{

    /**
     * @var Update
     */
    private $update;
    /**
     * @var Data
     */
    private $data;

    /**
     * WebHookUpdate constructor.
     * @param Update $update
     * @param Data $data
     */
    public function __construct(
        Update $update,
        Data $data
    ) {
        $this->update = $update;
        $this->data = $data;
    }

    /**
     * @param string $channelName
     * @param string $publicationStatus
     * @param string $productFeedExportUrl
     * @return array
     * @throws LocalizedException
     */
    public function update($channelName, $publicationStatus, $productFeedExportUrl)
    {
        $enabled = $this->data->getWebhookEnabled();
        if (!$enabled) {
            return ['status' => 'error'];
        }

        $this->update->hook([
            'channel_name' => $channelName,
            'publication_status' => $publicationStatus,
            'product_feed_export_url' => $productFeedExportUrl
        ]);
        return ['status' => 'success'];
    }
}
