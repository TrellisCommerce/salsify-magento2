<?php


namespace Trellis\Salsify\Controller\Adminhtml\System\Config\General;


use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Trellis\Salsify\Controller\Adminhtml\System\Config\Productfeed\Sync;
use Trellis\Salsify\Logger\Logger;

class Clear extends Action

{
    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var Sync
     */
    protected $_sync;

    /**
     * Button constructor.
     * @param Context $context
     * @param Logger $logger
     * @param Sync $sync
     */
    public function __construct(
        Context $context,
        Logger $logger,
        \Trellis\Salsify\Cron\Sync $sync
    ) {
        $this->_logger = $logger;
        $this->_sync   = $sync;

        parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        try {
            $this->_logger->info('salsify:sync manual button preparing execution.');
            $this->_sync->execute($context = 'manual:button', \Trellis\Salsify\Cron\Sync::SYNC_TYPE_CLEAR_SALSIFY_ID);
            $this->_logger->info('salsify:sync manual button execution complete.');
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }

}