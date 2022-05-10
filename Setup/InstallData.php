<?php

namespace Trellis\Salsify\Setup;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Resource\Eav\Attribute;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Class InstallData
 * @package Trellis\Salsify\Setup
 */
class InstallData implements InstallDataInterface
{
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * InstallData constructor.
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->removeAttribute(Product::ENTITY, 'trellis_salsify_id');
        $eavSetup->removeAttribute(Product::ENTITY, 'trellis_salsify_last_updated');
        $eavSetup->addAttribute(
            Product::ENTITY,
            'trellis_salsify_id',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Salsify ID',
                'input' => '',
                'class' => '',
                'source' => '',
                'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                'visible' => false,
                'required' => false,
                'user_defined' => false,
                'default' => null,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => true,
                'apply_to' => ''
            ]
        );
        $eavSetup->addAttribute(
            Product::ENTITY,
            'trellis_salsify_last_updated',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Salsify Last Updated Timestamp',
                'input' => '',
                'class' => '',
                'source' => '',
                'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                'visible' => false,
                'required' => false,
                'user_defined' => false,
                'default' => 0,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
    }
}
