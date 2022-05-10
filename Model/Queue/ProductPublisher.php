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

class ProductPublisher
{

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var RabbitMq
     */
    private $rabbitMq;

    /**
     * @param RabbitMq $rabbitMq
     */
    public function __construct(Rabbitmq $rabbitMq)
    {
        $this->rabbitMq = $rabbitMq;
    }

    /**
     * Init rabbitMq connection
     *
     * @throws \Exception
     */
    private function initConnection()
    {
        if (!$this->rabbitMq->isEnabled()) {
            throw new \Exception('The rabbitMq feature is disabled');
        }
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
    }

    /**
     * @param $product
     */
    public function execute($product)
    {
        $this->initConnection();
        $msg = new AMQPMessage($product);
        $this->channel->basic_publish($msg, '', Rabbitmq::QUEUE_NAME);
    }
}
