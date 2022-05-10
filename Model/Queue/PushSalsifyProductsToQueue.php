<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

declare(strict_types=1);

namespace Trellis\Salsify\Model\Queue;

use Magento\Framework\Serialize\SerializerInterface;
use Trellis\Salsify\Logger\Logger;

class PushSalsifyProductsToQueue
{
    /**
     * @var ProductPublisher
     */
    private $handler;

    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ProductPublisher $handler
     * @param SerializerInterface $serializer
     * @param Logger $logger
     */
    public function __construct(
        ProductPublisher $handler,
        SerializerInterface $serializer,
        Logger $logger
    ) {
        $this->handler = $handler;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public function execute(array $products)
    {
        foreach ($products as $product) {
            try {
                $this->handler->execute($this->serializer->serialize($product));
            } catch (\Exception $e) {
                $this->logger->info("ERROR RabbitMq");
                $this->logger->info($e->getMessage());
            }
        }
    }
}
