<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Logger\Logger;

/**
 * Class MediaDownloadManager
 * @package Trellis\Salsify\Model
 */
class MediaDownloadManager
{
    /**
     * @var File
     */
    private $file;
    /**
     * @var Data
     */
    private $configData;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * MediaDownloadManager constructor.
     * @param File $file
     * @param DirectoryList $directoryList
     * @param Data $configData
     * @param Logger $logger
     */
    public function __construct(
        File $file,
        DirectoryList $directoryList,
        Data $configData,
        Logger $logger
    ) {
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->configData = $configData;
        $this->logger = $logger;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getMediaPath()
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) .
            DIRECTORY_SEPARATOR . 'import' . DIRECTORY_SEPARATOR;
    }

    /**
     * @param $imageUrl
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function downloadImage($imageUrl)
    {
        if (!$this->file->isWriteable($this->getMediaPath())) {
            $this->file->mkdir($this->getMediaPath());
        }
        $newFileName = $this->getMediaPath() . baseName($imageUrl);
        $result = $this->file->read($imageUrl, $newFileName);
        if (!$result) {
            $this->logger->addCritical(
                sprintf(
                    'Error trying to download the image %s.',
                    $imageUrl
                )
            );
            return "";
        }
        return $newFileName;
    }

    /**
     * @param $image
     */
    public function delete($image)
    {
        $this->file->rm($image);
    }
}
