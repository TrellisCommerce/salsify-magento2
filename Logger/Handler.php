<?php 

namespace Trellis\Salsify\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Class Handler
 * @package Trellis\Salsify\Logger
 */
class Handler extends Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/trellis_salsify.log';
}
