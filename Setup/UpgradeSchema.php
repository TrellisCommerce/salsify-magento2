<?php
namespace Trellis\Salsify\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Store\Model\Store;

class UpgradeSchema implements UpgradeSchemaInterface
{
	public function upgrade( SchemaSetupInterface $setup, ModuleContextInterface $context ) {
		$installer = $setup;

		$installer->startSetup();

		if(version_compare($context->getVersion(), '1.4.14', '<')) {
			$table = $installer->getConnection()
            ->newTable($installer->getTable('salsify_webhook_payload'))
            ->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true
                ],
                'Entity ID'
            )
            ->addColumn(
                'processed',
                \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                null,
                [
                    'nullable' => false,
                    'default' => 0,
                ],
                'Payload Has Processed True or False'
            )
            ->addColumn(
                'payload',
                \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
                '2M',
                [
                    'nullable' => true
                ],
                'webhook payload'
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                [
                    'nullable' => false,
                    'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT
                ],
                'Created At'
            )
            ->addColumn(
                'processed_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                [
                    'nullable' => true,
                    'default' => null
                ],
                'Processed At'
            )
            ->setComment(
                'Salsify Webhook Payload Data'
            );
            $installer->getConnection()->createTable($table);
		}

        if(version_compare($context->getVersion(), '2.0.2', '<')) {
            $tableName = $setup->getTable('salsify_webhook_payload');
            if ($setup->getConnection()->isTableExists($tableName) == true) {
                $connection = $setup->getConnection();
                $connection->addColumn(
                    $tableName,
                    'store_view_id',
                    ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,'default' => Store::DEFAULT_STORE_ID,'comment'=>'Store View ID' ]
                );
            }
        }

        $installer->endSetup();
	}
}
