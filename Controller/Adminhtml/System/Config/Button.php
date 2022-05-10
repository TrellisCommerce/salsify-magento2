<?php

namespace Trellis\Salsify\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Trellis\Salsify\Cron\Sync;
use Trellis\Salsify\Logger\Logger;

/**
 * Class Button
 * @package Trellis\Salsify\Controller\Adminhtml\System\Config
 */
class Button extends Action
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Sync
     */
    private $sync;

    /**
     * Button constructor.
     * @param Context $context
     * @param Logger $logger
     * @param Sync $sync
     */
    public function __construct(
        Context $context,
        Logger $logger,
        Sync $sync
    ) {
        $this->logger = $logger;
        $this->sync = $sync;
        parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        try {
            $this->logger->info('salsify:sync manual button preparing execution.');
            $this->sync->execute($context = 'manual:button', $this->getRequest()->getParam('sync_type'));
            $this->logger->info('salsify:sync manual button execution complete.');
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
