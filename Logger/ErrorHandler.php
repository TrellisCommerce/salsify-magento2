<?php

namespace Trellis\Salsify\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class ErrorHandler extends Base
{

    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = Logger::ERROR;

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/salsify_error.log';
}
