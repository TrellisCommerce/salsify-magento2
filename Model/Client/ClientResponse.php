<?php
/**
 * Copyright 2020 (c) Trellis, All rights reserved.
 */

declare(strict_types=1);

namespace Trellis\Salsify\Model\Client;

use Trellis\Salsify\Api\ClientResponseInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class ClientResponse
 */
class ClientResponse implements ClientResponseInterface
{

    /** @var Json $serializer */
    private $serializer;

    /** @var $result */
    private $result;

    /**
     * ClientResponse constructor.
     *
     * @param Json $serializer
     */
    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @return Json
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

}
