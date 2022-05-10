<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Trellis\Salsify\Model\Config\Rabbitmq;
use Trellis\Salsify\Model\Queue\ProductConsumer as ModelProductConsumer;

class ProductConsumer extends Command
{
    /**
     * @var Rabbitmq
     */
    private $rabbitMq;
    /**
     * @var ModelProductConsumer
     */
    private $productConsumer;
    /**
     * @var State
     */
    private $state;

    /**
     * ProductConsumer constructor.
     * @param Rabbitmq $rabbitMq
     * @param ModelProductConsumer $productConsumer
     * @param State $state
     * @param null $name
     */
    public function __construct(
        Rabbitmq $rabbitMq,
        ModelProductConsumer $productConsumer,
        State $state,
        $name = null
    ) {
        parent::__construct($name);
        $this->rabbitMq = $rabbitMq;
        $this->productConsumer = $productConsumer;
        $this->state = $state;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('trellis:salsify:consume_product_queue');
        $this->setDescription('Disable backend reCaptcha');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_ADMINHTML);
        if (!$this->rabbitMq->isEnabled()) {
            $output->writeln("The message queue is disabled, but we are going to run it anyway");
        }
        $output->writeln("Running the message queue consumer");
        try {
            $this->productConsumer->consume();
        } catch (AMQPTimeoutException $e) {
            // silent, queue is empty.
        } catch (\Exception $e) {
            $output->writeln("Error " . $e->getMessage());
        }
        $output->writeln("Ended running message queue consumer");
    }
}
