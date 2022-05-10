<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Cron;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use Trellis\Salsify\Model\Config\Rabbitmq;
use Trellis\Salsify\Logger\Logger;
use Trellis\Salsify\Model\Queue\ProductConsumer;

class Consume
{
    /**
     * @var Rabbitmq
     */
    private $data;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var ProductConsumer
     */
    private $productConsumer;

    /**
     * Consume constructor.
     * @param Rabbitmq $data
     * @param Logger $logger
     * @param ProductConsumer $productConsumer
     */
    public function __construct(
        Rabbitmq $data,
        Logger $logger,
        ProductConsumer $productConsumer
    ) {
        $this->data = $data;
        $this->logger = $logger;
        $this->productConsumer = $productConsumer;
    }

    public function execute()
    {
        if (!$this->data->isEnabled()) {
            $this->logger->info("The message queue is disabled");
            return;
        }
        $this->logger->info("Running the message queue consumer");
        try {
            $this->productConsumer->consume();

        } catch (AMQPTimeoutException $e) {
            // silent, queue is empty.
        } catch (\Exception $e) {
            $this->logger->info("Error " . $e->getMessage());
        }
        $this->logger->info("Ended running message queue consumer");
    }
}
