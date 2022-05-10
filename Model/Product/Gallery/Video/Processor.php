<?php
/**
 * TRELLIS
 *
 * Date: 5/13/2019
 * Time: 3:32 PM
 *
 * @package Salsify
 * @author Travis Hill <travis@trellis.co>
 * @copyright 2019 Trellis (https://www.trellis.co)
 */

namespace Trellis\Salsify\Model\Product\Gallery\Video;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\CreateHandler;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Model\File\Uploader;

class Processor extends \Magento\Catalog\Model\Product\Gallery\Processor
{
    /**
     * @var CreateHandler
     */
    protected $createHandler;

    /**
     * Processor constructor.
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param Database $fileStorageDb
     * @param Config $mediaConfig
     * @param Filesystem $filesystem
     * @param Gallery $resourceModel
     * @param CreateHandler $createHandler
     * @throws FileSystemException
     */
    public function __construct(
        ProductAttributeRepositoryInterface $attributeRepository,
        Database $fileStorageDb,
        Config $mediaConfig,
        Filesystem $filesystem,
        Gallery $resourceModel,
        CreateHandler $createHandler
    ) {
        parent::__construct($attributeRepository, $fileStorageDb, $mediaConfig, $filesystem, $resourceModel);
        $this->createHandler = $createHandler;
    }

    /**
     * @param Product $product
     * @param array $videoData
     * @param array $mediaAttribute
     * @param bool $move
     * @param bool $exclude
     * @return string
     * @throws LocalizedException
     */
    public function addVideo(
        Product $product,
        array $videoData,
        $mediaAttribute = null,
        $move = false,
        $exclude = false
    ) {
        $file = $this->mediaDirectory->getRelativePath($videoData['thumbnail']);

        if (!$this->mediaDirectory->isFile($file)) {
            throw new LocalizedException(__('The image does not exist.'));
        }

        $pathinfo = pathinfo($file);
        $imgExtensions = ['jpg', 'jpeg', 'gif', 'png'];
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $imgExtensions)) {
            throw new LocalizedException(__('Please correct the image file type.'));
        }

        $fileName = Uploader::getCorrectFileName($pathinfo['basename']);
        $dispretionPath = Uploader::getDispretionPath($fileName);
        $fileName = $dispretionPath . '/' . $fileName;

        $fileName = $this->getNotDuplicatedFilename($fileName, $dispretionPath);

        $destinationFile = $this->mediaConfig->getTmpMediaPath($fileName);

        try {
            /** @var $storageHelper Database */
            $storageHelper = $this->fileStorageDb;
            if ($move) {
                $this->mediaDirectory->renameFile($file, $destinationFile);

                //Here, filesystem should be configured properly
                $storageHelper->saveFile($this->mediaConfig->getTmpMediaShortUrl($fileName));
            } else {
                $this->mediaDirectory->copyFile($file, $destinationFile);

                $storageHelper->saveFile($this->mediaConfig->getTmpMediaShortUrl($fileName));
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('We couldn\'t move this file: %1.', $e->getMessage()));
        }

        $fileName = str_replace('\\', '/', $fileName);

        $attrCode = $this->getAttribute()->getAttributeCode();
        $mediaGalleryData = $product->getData($attrCode);
        $position = 0;
        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = ['images' => []];
        }

        foreach ($mediaGalleryData['images'] as &$image) {
            if (isset($image['position']) && $image['position'] > $position) {
                $position = $image['position'];
            }
        }

        $position++;

        unset($videoData['file']);
        $mediaGalleryData['images'][] = array_merge([
            'file' => $fileName,
            'label' => $videoData['video_title'],
            'position' => $position,
            'disabled' => (int)$exclude
        ], $videoData);

        $product->setData($attrCode, $mediaGalleryData);

        if ($mediaAttribute !== null) {
            $product->setMediaAttribute($product, $mediaAttribute, $fileName);
        }

        $this->createHandler->execute($product);

        return $fileName;
    }
}
