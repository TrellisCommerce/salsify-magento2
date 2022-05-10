<?php

namespace Trellis\Salsify\Model\Sync;

use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\AttributeSetRepository;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Magento\Catalog\Model\Product\Attribute\SetRepository;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Trellis\Salsify\Logger\Logger;


class ProductAttributes
{
    const ATTRIBUTE_SET_GROUP_NAME = 'Imported From Salsify';
    const ATTRIBUTE_CODE_PREFIX    = "s_";

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Repository
     */
    protected $_attributeRepository;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\SetRepository
     */
    protected $_attributeSetRepository;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    protected $_attributeSetFactory;

    /* @var ProductAttributeManagementInterface */
    protected $_attributeManagement;

    /**
     * @var Logger
     */
    protected $_logger;

    /** @var $_eavSetup  EavSetupFactory */
    protected $_eavSetup;

    /** @var $_productEntityTypeId int */
    protected $_productEntityTypeId;

    /** @var $_attributeGroupIds array */
    protected $_attributeGroupIds = [];


    public function __construct(
        EavSetupFactory $eavEavSetupFactory,
        Repository $attributeRepository,
        SetRepository $attributeSetRepository,
        SetFactory $attributeSetFactory,
        ProductAttributeManagementInterface $attributeManagement,
        Logger $logger
    ) {
        $this->_eavSetup               = $eavEavSetupFactory->create();
        $this->_attributeRepository    = $attributeRepository;
        $this->_attributeSetRepository = $attributeSetRepository;
        $this->_attributeSetFactory    = $attributeSetFactory;
        $this->_attributeManagement    = $attributeManagement;
        $this->_logger                 = $logger;
    }

    /**
     * Create attributes in Magento from list of salsify attributes.
     * Looks for custom metadata on each attribute, and will only create attributes which contain this metadata.
     *
     * @param array $salsifyAttributes
     */
    public function createProductAttributes($salsifyAttributes)
    {
        $this->_addAttributeSetGroup();

        $createdAttributes = 0;
        foreach ($salsifyAttributes as $salsifyAttribute) {
            if (isset($salsifyAttribute['magento']) && $salsifyAttribute['magento']) {
                $attributeCode = $this->_getAttributeCode($salsifyAttribute['salsify:id']);
                $this->_logger->info("Creating attribute {$attributeCode} from Salsify in Magento.");

                try {
                    $this->_attributeRepository->get($attributeCode);

                    $this->_logger->info("Attribute {$attributeCode} already exists, skipping..");
                } catch (NoSuchEntityException $e) {
                    // If attribute does not exist, an exception is thrown.
                    // We then want to create the attribute
                    switch ($salsifyAttribute['salsify:data_type']) {
                        case 'boolean':
                            $type  = 'int';
                            $input = 'boolean';
                            break;
                        case 'string':
                            $type  = 'varchar';
                            $input = 'text';
                            break;
                        case 'number':
                            $type  = 'int';
                            $input = 'text';
                            break;
                        case 'digital_asset':
                        case 'enumerated':
                        default:
                            // Not Used (for now)
                            $type  = 'varchar';
                            $input = 'text';
                    }

                    $this->_logger->info("Mapped Salsify attribute {$attributeCode}. Type: {$type} - Input: {$input}.");

                    $this->_eavSetup->addAttribute(Product::ENTITY, $attributeCode, [
                        'type'         => $type,
                        'label'        => $salsifyAttribute['salsify:name'],
                        'group'        => self::ATTRIBUTE_SET_GROUP_NAME, // Add attribute to special salsify group name
                        'input'        => $input,
                        'required'     => false,
                        'visible'      => true,
                        'user_defined' => true,
                        'sort_order'   => 90,
                        'position'     => 999,
                        'system'       => 0,
                    ]);

                    $this->_logger->info("Created Salsify attribute in Magento {$attributeCode}.");

                    $createdAttributes++;
                }
            }
        }

        $this->_logger->info("Finished creating {$createdAttributes} attributes from Salsify.");
    }

    public function createAttributeSet($attributeSetName)
    {
        $this->_logger->info("Creating attribute set {$attributeSetName}");
        $entityTypeId = $this->_getProductEntityTypeId();

        ///* @var $attributeSet \Magento\Eav\Model\Entity\Attribute\Set */
        $attributeSet = $this->_attributeSetFactory->create();

        $defaultAttributeSetId = $this->_eavSetup->getDefaultAttributeSetId($entityTypeId);

        $data = [
            'attribute_set_name' => $attributeSetName,
            'entity_type_id'     => $entityTypeId,
            'sort_order'         => 200,
        ];

        $attributeSet->setData($data);
        $attributeSet->validate();
        $attributeSet->save();
        $attributeSet->initFromSkeleton($defaultAttributeSetId);
        $attributeSet->save();
    }

    public function addAttributesToSet(array $attributeCodes, $attributeSetName, $attributeGroupName)
    {
        $jsonAttributeCodes = json_encode($attributeCodes);
        $this->_logger->info("Adding attribute {$jsonAttributeCodes} to set {$attributeSetName} and group {$attributeGroupName}");

        $attributeSetData = $this->_eavSetup->getAttributeSet($this->_getProductEntityTypeId(), $attributeSetName);
        $attributesInSet  = $this->_attributeManagement->getAttributes($attributeSetData['attribute_set_id']);

        $attributeCodesInSet = [];
        foreach ($attributesInSet as $attribute) {
            $attributeCodesInSet[ $attribute->getAttributeCode() ] = $attribute;
        }

        foreach ($attributeCodes as $attributeCode) {
            $attributeCodePresent = isset($attributeCodesInSet[ $attributeCode ]);

            if (!$attributeCodePresent && !in_array($attributeCode, ['parent_id'])) {
                try {
                    $attribute = $this->_attributeRepository->get($attributeCode);
                    $this->_logger->info("Attribute {$attributeCode} does not exist in attribute set {$attributeSetName}, adding now..");
                    $this->_eavSetup->addAttributeToGroup($this->_getProductEntityTypeId(), $attributeSetData['attribute_set_id'], $this->_getAttributeGroupId($attributeSetData['attribute_set_id']), $attribute->getAttributeId());
                } catch (NoSuchEntityException $e) {
                    $this->_logger->info("Attribute {$attributeCode} does not exist! Cannot add to attribute set {$attributeSetName}.");
                }
            }
        }
    }

    /**
     * Create Salsify attribute set group or imported attributes
     */
    protected function _addAttributeSetGroup()
    {
        $entityTypeId    = $this->_getProductEntityTypeId();
        $attributeSetIds = $this->_eavSetup->getAllAttributeSetIds($entityTypeId);

        foreach ($attributeSetIds as $attributeSetId) {
            $attributeGroupId = $this->_eavSetup->getAttributeGroupId($entityTypeId, $attributeSetId, self::ATTRIBUTE_SET_GROUP_NAME);
            if (!$attributeGroupId) {
                $this->_eavSetup->addAttributeGroup($entityTypeId, $attributeSetId, self::ATTRIBUTE_SET_GROUP_NAME, 100);
            }
        }
    }

    protected function _getAttributeGroupId($attributeSetId)
    {
        if (!isset($this->_attributeGroupIds[ $attributeSetId ])) {
            $this->_attributeGroupIds[ $attributeSetId ] = $this->_eavSetup->getAttributeGroupId($this->_getProductEntityTypeId(), $attributeSetId, self::ATTRIBUTE_SET_GROUP_NAME);
        }

        return $this->_attributeGroupIds[ $attributeSetId ];
    }

    /**
     * Prefix all attribute codes
     *
     * @param string $attributeCode
     *
     * @return string
     */
    protected function _getAttributeCode($attributeCode)
    {
        return self::ATTRIBUTE_CODE_PREFIX . strtolower(str_replace(" ", "", $attributeCode));
    }

    protected function _getProductEntityTypeId()
    {
        if (is_null($this->_productEntityTypeId)) {
            $this->_productEntityTypeId = $this->_eavSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);
        }

        return $this->_productEntityTypeId;
    }
}