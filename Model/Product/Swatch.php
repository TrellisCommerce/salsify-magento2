<?php
/**
 * Swatch
 *
 * @copyright Copyright © 2020 Trellis, LLC. All rights reserved.
 * @author    csnedaker@trellis.co
 */
declare(strict_types=1);

namespace Trellis\Salsify\Model\Product;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory as SwatchCollectionFactory;
use Magento\Swatches\Model\SwatchAttributeType;
use Magento\Swatches\Model\SwatchAttributeCodes;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Swatches\Helper\Media as SwatchMediaHelper;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\AttributeRepository;
use Magento\Swatches\Model\Swatch as SwatchModel;
use Magento\Swatches\Model\SwatchFactory;
use Magento\Catalog\Model\Product\Attribute\Repository as ProductAttributeRepository;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem;
use Trellis\Salsify\Logger\Logger;

/**
 * Class for working with Swatch functionality within Salsify
 */
class Swatch
{

    /** @var Logger */
    private $logger;

    /** @var File $ioFile */
    private $ioFile;

    /** @var Filesystem $filesystem */
    private $filesystem;

    /** @var DirectoryList $directoryList */
    private $directoryList;

    /** @var SwatchMediaHelper $swatchMediaHelper */
    private $swatchMediaHelper;

    /** @var SwatchHelper $swatchHelper */
    private $swatchHelper;

    /** @var SwatchFactory $swatchFactory */
    private $swatchFactory;

    /** @var SwatchAttributeCodes $swatchAttributeCodes */
    private $swatchAttributeCodes;

    /** @var SwatchAttributeType $swatchAttributeType */
    private $swatchAttributeType;

    /** @var AttributeRepository */
    private $attributeRepository;

    /** @var ProductAttributeRepository $productAttributeRepository */
    private $productAttributeRepository;

    /** @var SwatchCollectionFactory $swatchCollectionFactory */
    private $swatchCollectionFactory;

    /** @var Filesystem\Directory\WriteInterface */
    private $mediaDirectory;

    /** @var array $data */
    private $data;

    private $attribute = null;

    /**
     * Swatch constructor.
     *
     * @param Filesystem                 $filesystem
     * @param File                       $ioFile
     * @param DirectoryList              $directoryList
     * @param SwatchMediaHelper          $swatchMediaHelper
     * @param SwatchHelper               $swatchHelper
     * @param SwatchAttributeCodes       $swatchAttributeCodes
     * @param AttributeRepository        $attributeRepository
     * @param SwatchFactory              $swatchFactory
     * @param SwatchModel                $swatch
     * @param ProductAttributeRepository $productAttributeRepository
     * @param SwatchCollectionFactory    $swatchCollectionFactory
     * @param Logger                     $logger
     * @param SwatchAttributeType        $swatchAttributeType
     * @param array                      $data
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        File $ioFile,
        DirectoryList $directoryList,
        SwatchMediaHelper $swatchMediaHelper,
        SwatchHelper $swatchHelper,
        SwatchAttributeCodes $swatchAttributeCodes,
        AttributeRepository $attributeRepository,
        SwatchFactory $swatchFactory,
        SwatchModel $swatch,
        ProductAttributeRepository $productAttributeRepository,
        SwatchCollectionFactory $swatchCollectionFactory,
        Logger $logger,
        SwatchAttributeType $swatchAttributeType,
        array $data = []
    ) {
        $this->filesystem = $filesystem;
        $this->ioFile = $ioFile;
        $this->directoryList = $directoryList;
        $this->swatchMediaHelper = $swatchMediaHelper;
        $this->swatchHelper = $swatchHelper;
        $this->swatchFactory = $swatchFactory;
        $this->swatchAttributeCodes = $swatchAttributeCodes;
        $this->attributeRepository = $attributeRepository;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->swatchCollectionFactory = $swatchCollectionFactory;
        $this->logger = $logger;
        $this->swatchAttributeType = $swatchAttributeType;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->data = $data;
    }

    /**
     * @param string $attributeCode
     * @param        $optionId
     * @param string $swatchValue
     * @param null   $storeId
     *
     * @return bool
     */
    public function saveSwatch(string $attributeCode, $optionId, string $swatchValue, $storeId = null)
    {
        $value = null;

        try {
            $attribute = $this->attributeRepository->get(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );
        } catch (NoSuchEntityException $exception) {
            $this->logger->addError($exception->getMessage());
        }

        if ($this->isAttributeCodeSwatch($attributeCode)) {

            if ($this->isVisualSwatch($attribute)) {
                if ($swatchValue[0] === '#') {
                    $value = $swatchValue;
                } else {
                    $value = $this->saveSwatchImage($swatchValue);
                }
                $attribute->setData('swatch', ['value' => [$optionId => $value]]);
            }

            if ($this->isTextSwatch($attribute)) {
                if ($storeId) {
                    $value = [$storeId => $swatchValue];
                } else {
                    $value = [$swatchValue];
                }
                $attribute->addData(['swatchtext' => ['value' => [$optionId => $value]]]);
            }

            if ($value) {
                try {
                    $attribute->save();

                    return true;
                } catch (NoSuchEntityException $exception) {
                    $this->logger->error($exception->getMessage());
                } catch (CouldNotSaveException $exception) {
                    $this->logger->error($exception->getMessage());
                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        }

        return false;
    }

    public function getAttributeFromCode($attributeCode)
    {
        try {
            $this->attribute = $this->attributeRepository->get(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );
        } catch (NoSuchEntityException $exception) {
            $this->logger->addError($exception->getMessage());
        }

        return $this->attribute;

    }

    /**
     * @param $attribute
     *
     * @return bool
     */
    public function isTextSwatch($attribute = null)
    {
        if (is_string($attribute)) {
            $attribute = $this->getAttributeFromCode($attribute);
        }
        if (!$attribute) {
            $attribute = $this->attribute;
        }

        return $this->swatchAttributeType->isTextSwatch($attribute);
    }

    /**
     * @param $attribute
     *
     * @return bool
     */
    public function isVisualSwatch($attribute = null)
    {
        if (is_string($attribute)) {
            $attribute = $this->getAttributeFromCode($attribute);
        }

        if (!$attribute) {
            $attribute = $this->attribute;
        }

        return $this->swatchAttributeType->isVisualSwatch($attribute);
    }

    /**
     * @param $attribute
     */
    public function isSwatchAttribute($attribute)
    {
        return $this->swatchAttributeType->isSwatchAttribute($attribute);
    }

    /**
     * @param $imageUrl
     *
     * @return false|string
     */
    public function saveSwatchImage($imageUrl)
    {
        $filename = $this->filePathInfo($imageUrl, 'basename');
        $swatchFile = $this->generateSwatchFileValue($filename);
        $destinationFile = $this->getDestinationFile($swatchFile);
        if ($this->checkSwatchSavePaths($destinationFile)) {
            if (!$this->ioFile->fileExists($destinationFile)) {
                if (!$this->ioFile->read($imageUrl, $destinationFile)) {
                    return false;
                }
            }
        }

        return $swatchFile;
    }

    /**
     * @param $swatchFile
     *
     * @return string
     */
    private function getDestinationFile($swatchFile): string
    {
        return $this->mediaDirectory->getAbsolutePath($this->swatchMediaHelper->getAttributeSwatchPath($swatchFile));
    }

    /**
     * @param $filename
     *
     * @return string
     */
    private function generateSwatchFileValue($filename): string
    {
        $fileName = \Magento\MediaStorage\Model\File\Uploader::getCorrectFileName($filename); // red.png
        $dispersionPath = \Magento\MediaStorage\Model\File\Uploader::getDispersionPath($fileName); // /r/e

        return $dispersionPath . '/' . $fileName; // /r/e/red.png
    }

    /**
     * part can be one of: dirname, basename, extension, filename
     *
     * @param      $file
     * @param null $part
     *
     * @return mixed
     */
    public function filePathInfo($file, $part = null)
    {
        $pathinfo = $this->ioFile->getPathInfo($file);
        if ($part) {
            return $pathinfo[$part];
        }

        return $pathinfo;
    }

    /**
     * @param $file
     *
     * @return bool
     */
    private function checkSwatchSavePaths($file): bool
    {
        $this->ioFile->setAllowCreateFolders(true);
        try {
            $this->ioFile->checkAndCreateFolder($this->filePathInfo($file, 'dirname'));
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return SwatchHelper
     */
    public function getSwatchHelper(): SwatchHelper
    {
        return $this->swatchHelper;
    }

    /**
     * This method is not recommended -- it doesn't account for swatch attributes without any created options
     *
     * @return array
     */
    public function getSwatchAttributeCodes(): array
    {
        return $this->swatchAttributeCodes->getCodes();
    }

    /**
     * @param $attributeCode
     *
     * @return false|\Magento\Eav\Api\Data\AttributeInterface
     */
    public function getProductAttributeFromCode($attributeCode)
    {
        try {
            $attribute = $this->attributeRepository->get(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );

            return $attribute;
        } catch (NoSuchEntityException $exception) {
            $this->logger->error($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }

        return false;
    }

    /**
     * Dont use -- falls short.
     *
     * @return array
     */
    public function getSwatchAttributeCodeArray(): array
    {
        $codes = [];
        foreach ($this->getSwatchAttributeCodes() as $key => $v) {
            $codes[] = $v;
        }

        return $codes;
    }

    /**
     * @param string $attributeCode
     *
     * @return bool
     */
    public function isAttributeCodeSwatch(string $attributeCode): bool
    {
        if (!$this->attribute) {
            $this->attribute = $this->getProductAttributeFromCode($attributeCode);
        }

        if ($this->attribute) {
            return $this->isSwatchAttribute($this->attribute);
        }

        return false;
    }
}
