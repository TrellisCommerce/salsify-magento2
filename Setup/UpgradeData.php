<?php


namespace Trellis\Salsify\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;


class UpgradeData implements UpgradeDataInterface
{
    public function __construct(
        EavSetupFactory $eavSetupFactory
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        if (version_compare($context->getVersion(), '2.0.1', '<')) {
            $updateAttributes = array('trellis_salsify_id','trellis_salsify_last_updated');

            foreach ($updateAttributes as $attribute) {
                $eavSetup->updateAttribute(
                    \Magento\Catalog\Model\Product::ENTITY,
                    $attribute,
                    'global',
                    \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE
                );
            }
        }
    }
}