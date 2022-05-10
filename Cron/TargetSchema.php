<?php

namespace Trellis\Salsify\Cron;

use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Logger\Logger;

/**
 * Class Sync
 * @package Trellis\Salsify\Cron
 */
class TargetSchema
{
    private $data;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var TargetSchema
     */
    private $salsifyExportTargetSchema;

    /**
     * Sync constructor.
     *
     * @param Data                        $data
     * @param Logger                      $logger
     * @param \Trellis\Salsify\Model\Sync $exportTargetSchema
     */
    public function __construct(
        Data $data,
        Logger $logger,
        \Trellis\Salsify\Model\TargetSchema $exportTargetSchema
    ) {
        $this->data                      = $data;
        $this->logger                    = $logger;
        $this->salsifyExportTargetSchema = $exportTargetSchema;
    }

    /**
     * @param string $context
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateTargetSchema()
    {
        $schema   = $this->salsifyExportTargetSchema->generateAsJson();
        $filePath = $this->salsifyExportTargetSchema->writeSchemaToFile(
            $schema,
            \Trellis\Salsify\Model\TargetSchema::FILENAME
        );

        return $filePath;
    }
}
