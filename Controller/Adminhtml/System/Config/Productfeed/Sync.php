<?php

namespace Trellis\Salsify\Controller\Adminhtml\System\Config\Productfeed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Trellis\Salsify\Logger\Logger;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class Button
 * @package Trellis\Salsify\Controller\Adminhtml\System\Config
 */
class Sync extends Action
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
     * @var JsonFactory
     */
    protected $_jsonResultFactory;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param JsonFactory $jsonResultFactory
     * @param \Trellis\Salsify\Cron\Sync $sync
     */
    public function __construct(
        Context $context,
        Logger $logger,
        JsonFactory $jsonResultFactory,
        \Trellis\Salsify\Cron\Sync $sync
    ) {
        $this->_logger = $logger;
        $this->_jsonResultFactory = $jsonResultFactory;
        $this->_sync   = $sync;

        parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        try {
            $result = $this->_jsonResultFactory->create();
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);
            $this->_logger->info('salsify:sync manual button preparing execution.');
            $this->_sync->execute($context = 'manual:button', \Trellis\Salsify\Cron\Sync::SYNC_TYPE_PRODUCT_FEED);
            $this->_logger->info('salsify:sync manual button execution complete.');
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
            $result->setJsonData(json_encode(['error_message'=>"Something wrong happened. \n Please contact our helpdesk."]));
        }
        return $result;
    }
}
