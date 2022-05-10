<?php
/**
 * @author    Trellis Team
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
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;

class CreateTestCategories extends Command
{
    protected $categoryFactory;
    protected $categoryRepository;
    protected $state;

    /**
     * ProductConsumer constructor.
     *
     * @param Rabbitmq             $rabbitMq
     * @param ModelProductConsumer $productConsumer
     * @param State                $state
     * @param null                 $name
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        CategoryRepositoryInterface $categoryRepository,
        State $state,
        $name = null
    ) {
        parent::__construct($name);

        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->state = $state;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('trellis:salsify:create_category');
        $this->setDescription('Create category script');
        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($i = 3; $i < 220; $i++) {
            $category = $this->categoryFactory->create();
            $category->setName("Test category {$i}");
            $category->setParentId(2);
            $category->setIsActive(true);
            $category->setCustomAttributes([
                                               'description'      => "Test category description {$i}",
                                               'meta_title'       => "Test category meta title {$i}",
                                               'meta_keywords'    => "Test category meta keywords {$i}",
                                               'meta_description' => "Test category meta description {$i}",
                                           ]);

            $this->categoryRepository->save($category);
        }
    }
}
