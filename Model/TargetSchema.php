<?php

namespace Trellis\Salsify\Model;

use \Magento\Eav\Api\AttributeRepositoryInterface;
use \Magento\Framework\Api\SearchCriteriaBuilder;
use \Magento\Framework\Api\Filter;
use \Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Helper\Product;
use Trellis\Salsify\Helper\ReadinessReports;
use Trellis\Salsify\Logger\Logger;

/**
 * Class TargetSchema
 * @package Trellis\Salsify\Model
 */
class TargetSchema
{
    const SALSIFY_DATA_TYPE_DIGITAL_ASSET = 'digital_asset';
    const SALSIFY_DATA_TYPE_STRING        = 'string';

    const FILENAME = 'salsify_target_schema.json';

    /**
     * @var Logger
     */
    protected $_logger;
    /**
     * @var AttributeRepositoryInterface
     */
    protected $_attributeRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;
    /**
     * @var Filesystem
     */
    protected $_filesystem;
    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    protected $_readinessReportsHelper;

    protected $_url;

    /** @var Product */
    protected $_productHelper;

    /** @var Data */
    protected $_dataHelper;

    /**
     * TargetSchema constructor.
     *
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        Logger $logger,
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Filesystem $filesystem,
        ReadinessReports $readinessReportsHelper,
        SerializerInterface $serializer,
        UrlInterface $url,
        Product $productHelper,
        Data $dataHelper
    ) {
        $this->_logger                 = $logger;
        $this->_attributeRepository    = $attributeRepository;
        $this->_searchCriteriaBuilder  = $searchCriteriaBuilder;
        $this->_filesystem             = $filesystem;
        $this->_readinessReportsHelper = $readinessReportsHelper;
        $this->_serializer             = $serializer;
        $this->_url                    = $url;
        $this->_productHelper          = $productHelper;
        $this->_dataHelper             = $dataHelper;
    }

    /**
     * Generate the target schema.
     *
     * @return array
     */
    public function generate()
    {
        $this->_logger->info("Generating target schema.");
        $targetSchema = [
            "display_names"       => null,
            "parent_id_field_ids" => [],
            "product_id_field_id" => null,
            "fields"              => [],
        ];

        $searchCriteria = $this->_searchCriteriaBuilder->create();

        // We default to filtering out the system required non visible attributes
        $filterSystemAttributes = new Filter();
        $filterSystemAttributes->setField('attribute_code')
            ->setValue($this->_productHelper->getSystemRequiredNonVisibleAttributes())
            ->setConditionType('nin');

        $filterGroup = new FilterGroup();
        $filterGroup->setFilters([$filterSystemAttributes]);

        // If there are any attributes defined in system config, use those as the basis for the target schema json file
        $attributesToExport = $this->_readinessReportsHelper->getTargetSchemaAttributes();
        if (empty($attributesToExport)) {
            $this->_logger->info("No attributes defined in system config to export. Exporting all product attributes.");
        } else {
            // If we have specific attributes we'd like to export, then we override the filters and only export the attributes specified
            $this->_logger->info(count($attributesToExport) . " attributes defined in system config to export. {$this->_serializer->serialize($attributesToExport)}");
            $attributeIds = array_map(function ($attribute) {
                return $attribute['attribute'];
            }, $attributesToExport);

            $filterExportAttributes = new Filter();
            $filterExportAttributes->setField('attribute_id')
                ->setValue($attributeIds)
                ->setConditionType('in');

            $filterGroup->setFilters([$filterExportAttributes]);
        }

        $searchCriteria->setFilterGroups([$filterGroup]);

        $attributeRepository = $this->_attributeRepository->getList(
            'catalog_product',
            $searchCriteria
        );

        $this->_logger->info("Found {$attributeRepository->getTotalCount()} attributes to export.");
        $list = $attributeRepository->getItems();
        foreach ($list as $productAttribute) {
            // Skip over any attributes that might be defined by one of our constants
            if (in_array($productAttribute->getAttributeCode(), Sync::TRELLIS_SALSIFY_ATTRIBUTES)) {
                continue;
            }

            switch ($productAttribute->getFrontendInput()) {
                case 'media_image':
                case 'gallery':
                    $dataType = self::SALSIFY_DATA_TYPE_DIGITAL_ASSET;
                    break;
                default:
                    $dataType = self::SALSIFY_DATA_TYPE_STRING;
                    break;
            }

            $attributeSchemaTemplate = [
                "external_id"             => '',
                "name"                    => '',
                "html_description"        => "<div style=\"margin-top: -1em\">\n\n\</div>",
                "required"                => false,
                "classifier"              => false,
                "data_type"               => '',
                "field_group_external_id" => '',
                "read_only"               => null,
                "field_values"            => [],
                "applicable_scopes"       => [],
                "requirements"            => [],
            ];

            if ($productAttribute->getIsVisible()) {
                $attributeSchema = [
                    "external_id" => $productAttribute->getAttributeCode(),
                    "name"        => $productAttribute->getAttributeCode(),
                    "required"    => $productAttribute->getIsRequired(),
                    "data_type"   => $dataType,
                ];

                $targetSchema['fields'][] = array_merge($attributeSchemaTemplate, $attributeSchema);
            }
        }

        // Add some default attributes to the target schema
        $defaultAttributes = [
            [
                'external_id' => Sync::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_PARENT_ID_ATTRIBUTE)),
                'required'    => true,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_ID_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_ID_ATTRIBUTE)),
                'required'    => true,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_ATTRIBUTE_SET_CODE_ATTRIBUTE)),
                'required'    => true,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_PRODUCT_TYPE_ATTRIBUTE)),
                'required'    => true,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_CONFIGURABLE_ATTRIBUTES_ATTRIBUTE)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_CATEGORY_ID_ATTRIBUTE)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_MEDIA_GALLERY_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_MEDIA_GALLERY_ATTRIBUTE)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_DIGITAL_ASSET,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_CONSTANT_SYSTEM_ATTRIBUTE)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_BUNDLED_SKUS,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_BUNDLED_SKUS)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_GROUPED_SKUS,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_GROUPED_SKUS)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_RELATED_SKUS,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_RELATED_SKUS)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_CROSSSELL_SKUS,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_CROSSSELL_SKUS)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
            [
                'external_id' => Sync::TRELLIS_SALSIFY_UPSELL_SKUS,
                'name'        => ucwords(str_replace('_', ' ', Sync::TRELLIS_SALSIFY_UPSELL_SKUS)),
                'required'    => false,
                'data_type'   => self::SALSIFY_DATA_TYPE_STRING,
            ],
        ];

        foreach ($defaultAttributes as $defaultAttribute) {
            $targetSchema['fields'][] = array_merge($attributeSchemaTemplate, $defaultAttribute);
        }

        return $targetSchema;
    }

    /**
     * Gennerate the target schema as a json string.
     *
     * @return bool|string
     */
    public function generateAsJson()
    {
        return $this->_serializer->serialize($this->generate());
    }

    /**
     * Write the target schema json string to a file.
     *
     * @param string $schema
     * @param string $filePath
     *
     * @return string
     */
    public function writeSchemaToFile($schema, $filePath)
    {
        try {
            $jsonDir = $this->_filesystem->getDirectoryWrite(DirectoryList::PUB);

            $this->_logger->info("Writing target schema to file. Using directory {$jsonDir->getAbsolutePath()}.");

            $jsonDir->writeFile($filePath, $schema);

            $relativePath = $jsonDir->getRelativePath(DirectoryList::PUB . '/' . $filePath);

            $this->_logger->info("Finished writing to file. Location: {$relativePath}");

            return $relativePath;
        } catch (FileSystemException $e) {
        }
    }
}
