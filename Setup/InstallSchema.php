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

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;

class InstallSchema implements InstallSchemaInterface
{

    /**
     * Install the Picup data
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        /**
         * Create table 'picup_warehouse_shifts'
         */
        $table = $installer->getConnection()->newTable(
            $installer->getTable('picup_warehouse_shifts')
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
            'delivery_day',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'Delivery Day'
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
            'cutoff_time',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            ['nullable' => false],
            'Cutoff Time'
        )->addColumn(
            'description',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Description'
        )->addColumn(
            'price',
            \Magento\Framework\DB\Ddl\Table::TYPE_NUMERIC,
            null,
            ['nullable' => false],
            'Price'
        )->addIndex(
            $setup->getIdxName(
                $installer->getTable('picup_warehouse_shifts'),
                ['store_id'],
                AdapterInterface::INDEX_TYPE_INDEX
            ),
            ['store_id'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->setComment(
            'Picup Shift Table'
        );
        $installer->getConnection()->createTable($table);

        /**
         * Create table 'picup_products'
         */

        $table = $installer->getConnection()->newTable(
            $installer->getTable('picup_products')
        )->addColumn(
            'id',
            \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true],
            'ID'
        )->addColumn(
            'product_sku',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Product Sku'
        )->addColumn(
            'picup_enabled',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'Picup Enabled for Product'
        )->addColumn(
            'picup_width',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'Product Width'
        )->addColumn(
            'picup_length',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'Product Length'
        )->addColumn(
            'picup_height',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'Product Height'
        )->addColumn(
            'store_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'Store ID'
        )->addColumn(
            'shift_data',
            \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
            null,
            ['nullable' => false],
            'Shift Data'
        )->setComment(
            'Picup Product Table'
        );
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }
}
