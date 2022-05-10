<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Api;

interface WebHookUpdateInterface
{
    /**
     * @param string $channelName
     * @param string $publicationStatus
     * @param string $productFeedExportUrl
     * @return mixed
     */
    public function update($channelName, $publicationStatus, $productFeedExportUrl);
}