<?php


namespace Trellis\Salsify\Controller\Adminhtml\System\Config\Debug;


use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Trellis\Salsify\Controller\Adminhtml\System\Config\Productfeed\Sync;
use Trellis\Salsify\Logger\Logger;

class Debug extends Action

{
    /**
     * @var Logger
     */
    protected $_logger;
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * Button constructor.
     * @param Context $context
     * @param Logger $logger
     * @param Sync $sync
     */
    public function __construct(
        Context $context,
        Logger $logger,
        DirectoryList $directoryList
    ) {
        $this->_logger = $logger;
        parent::__construct($context);
        $this->directoryList = $directoryList;
    }

    /**
     *
     */
    public function execute()
    {
        $var = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        $file = $var.'/log/trellis_salsify.log';
        try {
            unlink($file);
            touch($file);
            $this->_logger->info('Debug logs cleared from admin.');
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }

}