<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Model\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Trellis\Salsify\Model\Config\Rabbitmq;
use Trellis\Salsify\Model\Sync\ProductFeed;

class ProductConsumer
{
    /**
     * @var Sync
     */
    private $sync;

    /**
     * @var array
     */
    private $attributeFeed;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    private $channel;

    /**
     * @var int
     */
    private $count = 0;
    /**
     * @var Rabbitmq
     */
    private $rabbitMq;

    /**
     * ProductConsumer constructor.
     * @param Sync $sync
     * @param Rabbitmq $rabbitMq
     */
    public function __construct(
        ProductFeed $sync,
        Rabbitmq $rabbitMq
    ) {
        $this->sync = $sync;
        $this->rabbitMq = $rabbitMq;
    }

    /**
     * @param AMQPMessage $message
     * @throws \Exception
     */
    public function processMessage($message)
    {
        try {
            $salsifyProducts = [json_decode($message->getBody(), true)];

            $salsifyAttributes = $this->getAttributeFeed();

            // Update existing products, and create non-existent ones:
            $this->sync->upsertProducts($salsifyProducts, $salsifyAttributes);

            // Optionally delete Salsify-sync'd records that are no longer in the Salsify response:
            $this->sync->deleteProducts(array_map(function ($salsifyProduct) {
                return $salsifyProduct[ProductFeed::SALSIFY_ID_KEY];
            }, $salsifyProducts), true);

            /** @var AMQPChannel $channel */
            $channel = $message->delivery_info['channel'];
            $channel->basic_ack($message->delivery_info['delivery_tag']);

            $this->count++;

        } catch (\Exception $e) {
            throw $e;
        }

    }

    /**
     * @return array
     */
    private function getAttributeFeed()
    {
        if (!$this->attributeFeed) {
            $this->attributeFeed = $this->sync->getClient()->getAttributeFeed();
        }
        return $this->attributeFeed;
    }

    /**
     *
     */
    public function consume()
    {
        $this->initConnection();
        $this->count = 0;
        $this->channel->basic_consume(
            Rabbitmq::QUEUE_NAME,
            '',
            false,
            false,
            false,
            false,
            [$this, 'processMessage']
        );
        while ($this->count < $this->rabbitMq->getPagination()) {
            $this->channel->wait(null, false, 1);
        }
    }

    /**
     * Initialize the connection
     */
    private function initConnection()
    {
        $connection = new AMQPStreamConnection(
            $this->rabbitMq->getHost(),
            $this->rabbitMq->getPort(),
            $this->rabbitMq->getUser(),
            $this->rabbitMq->getPassword(),
            $this->rabbitMq->getVhost()
        );
        $this->channel = $connection->channel();
        $this->channel->queue_declare(
            Rabbitmq::QUEUE_NAME,
            false,
            true,
            false,
            false
        );
        $this->channel->basic_qos(null, $this->rabbitMq->getPagination(), null);
    }
}
