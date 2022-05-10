<?php

namespace Trellis\Salsify\Controller\Adminhtml\System\Config\Readinessreports;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Trellis\Salsify\Cron\TargetSchema;
use Trellis\Salsify\Logger\Logger;
use \Trellis\Salsify\Model\ClientFactory;


/**
 * Class ExportTargetSchema
 * @package Trellis\Salsify\Controller\Adminhtml\System\Config
 */
class ExportTargetSchema extends Action
{
    /**
     * @var Logger
     */
    protected $_logger;
    /**
     * @var ExportTargetSchema
     */
    protected $_targetSchema;

    protected $_client;

    /**
     * ExportTargetSchema constructor.
     *
     * @param Context      $context
     * @param Logger       $logger
     * @param TargetSchema $targetSchema
     */
    public function __construct(
        Context $context,
        Logger $logger,
        TargetSchema $targetSchema,
        ClientFactory $clientFactory
    ) {
        $this->_logger       = $logger;
        $this->_targetSchema = $targetSchema;
        $this->_client       = $clientFactory->create();
        parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        try {
            $this->_logger->info('salsify:export_target_schema manual button preparing execution.');
            $filePath = $this->_targetSchema->generateTargetSchema();

            $this->_logger->info("Ready to send Target Schema JSON located at {$filePath} to Salsify");

            $response = $this->_client->uploadTargetSchema($filePath);

            if (!empty($response)) {
                $this->_logger->info("Response: {$response['message']} Code: {$response['code']}");
            } else {
                $this->_logger->info("Response is empty.");
            }

            $this->_logger->info('salsify:export_target_schema manual button execution complete.');
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }
}
