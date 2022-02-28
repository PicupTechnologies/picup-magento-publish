<?php
/**
 * Copyright 2020  Picup Technology (Pty) Ltd or its affiliates. All Rights Reserved.
 *
 * Licensed under the GNU General Public License, Version 3.0 or later(the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  https://opensource.org/licenses/GPL-3.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Picup\Shipping\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (version_compare($context->getVersion(), '0.0.3', '<')) {

            if ($installer->tableExists("picup_warehouse_shifts")) {
                $tableName = $installer->getTable("picup_warehouse_shifts");
                $installer->getConnection()->addColumn($tableName,
                    'picup_zones', [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        null,
                        'nullable' => true,
                        'comment' => 'Picup Bucket Zones'
                    ]
                );
            }

            if (!$installer->tableExists("picup_warehouse_zones")) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('picup_warehouse_zones')
                )->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    null,
                    ['identity' => true, 'nullable' => false, 'primary' => true],
                    'ID'
                )->addColumn(
                    'store_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false],
                    'Store ID'
                )->addColumn(
                    'postal_codes',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Postal Codes'
                )->addColumn(
                    'shift_start',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    10,
                    ['nullable' => false],
                    'Shift Start'
                )->addColumn(
                    'shift_end',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    10,
                    ['nullable' => false],
                    'Shift End'
                )->addColumn(
                    'cutoff_hours',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    10,
                    ['nullable' => false],
                    'Cutoff Hours'
                )->addColumn(
                    'description',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Description'
                )->addColumn(
                    'consignment_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Consignment Id'
                )->addColumn(
                    'price',
                    \Magento\Framework\DB\Ddl\Table::TYPE_NUMERIC,
                    null,
                    ['nullable' => false],
                    'Price'
                )->addIndex(
                    $setup->getIdxName(
                        $installer->getTable('picup_warehouse_zones'),
                        ['store_id'],
                        AdapterInterface::INDEX_TYPE_INDEX
                    ),
                    ['store_id'],
                    ['type' => AdapterInterface::INDEX_TYPE_INDEX]
                )->setComment(
                    'Picup Zone Table'
                );

                $installer->getConnection()->createTable($table);
            }

        }

        if (version_compare($context->getVersion(), '0.0.4', '=')) {
            if ($installer->tableExists("picup_warehouse_zones")) {
                $installer->getConnection()->addColumn(
                    $installer->getTable('picup_warehouse_zones'),
                        'show_zone',
                        [
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'length' => 10,
                        'nullable' => true,
                        'comment' => 'Show Zone'
                        ]
                    );

                $installer->getConnection()->addColumn(
                    $installer->getTable('picup_warehouse_zones'),
                    'postal_codes_ignore',
                    [
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Postal Codes Ignore'
                    ]
                );

                $installer->getConnection()->addColumn(
                    $installer->getTable('picup_warehouse_shifts'),
                    'same_day_caption',
                    [
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Same Day Caption'
                    ]
                );

                $installer->getConnection()->addColumn(
                    $installer->getTable('picup_warehouse_shifts'),
                    'next_day_caption',
                    [
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Next Day Caption'
                    ]
                );

            }
        }


        $installer->endSetup();
    }
}
