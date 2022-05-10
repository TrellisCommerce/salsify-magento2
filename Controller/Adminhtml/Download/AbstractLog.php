<?php

namespace Trellis\Salsify\Controller\Adminhtml\Download;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Controller\Adminhtml\System;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\NotFoundException;
use Zend_Filter_BaseName;

/**
 * Class AbstractLog
 * @package Trellis\Salsify\Controller\Adminhtml\Download
 */
abstract class AbstractLog extends System
{
    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * AbstractLog constructor.
     * @param Context $context
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory
    ) {
        $this->fileFactory = $fileFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws NotFoundException
     */
    public function execute()
    {
        $param = $this->getRequest()->getParams();

        $pathInfo = pathinfo($param[0]);

        // Kinda hacky way around this
        // All json files are in var/tmp for now..
        if ($pathInfo['extension'] === 'json') {
            $filePath = $this->getPubFilePathWithFile($param[0]);
        } else {
            $filePath = $this->getLogFilePathWithFile($param[0]);
        }

        $filter = new Zend_Filter_BaseName();
        $fileName = $filter->filter($filePath);
        try {
            return $this->fileFactory->create(
                $fileName,
                [
                    'type' => 'filename',
                    'value' => $filePath
                ]
            );
        } catch (\Exception $e) {
            throw new NotFoundException(__($e->getMessage()));
        }
    }

    /**
     * @param $fileName
     * @return mixed
     */
    abstract protected function getLogFilePathWithFile($fileName);

    /**
     * @param $fileName
     * @return mixed
     */
    abstract protected function getPubFilePathWithFile($fileName);
}
