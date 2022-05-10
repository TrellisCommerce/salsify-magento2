<?php

namespace Trellis\Salsify\Controller\Adminhtml\Download;

/**
 * Class GetFile
 * @package Trellis\Salsify\Controller\Adminhtml\Download
 */
class GetFile extends AbstractLog
{
    /**
     * @param $fileName
     *
     * @return string
     */
    protected function getLogFilePathWithFile($fileName)
    {
        return 'var/log/' . $fileName;
    }

    /**
     * @param $fileName
     *
     * @return string
     */
    protected function getPubFilePathWithFile($fileName)
    {
        return 'pub/' . $fileName;
    }
}
