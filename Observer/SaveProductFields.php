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

namespace Picup\Shipping\Observer;
use Magento\Framework\Event\ObserverInterface;

class SaveProductFields implements ObserverInterface {

    private $_request;
    private $_objectManager;
    private $_resourceConnection;


    public function __construct(
        \Magento\Framework\App\Action\Context $context
        //other objects
    ) {
        $this->context     = $context;
        $this->_request   = $context->getRequest();
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_resourceConnection = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
        //other objects
    }

    /**
     * @param Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $params               = $this->_request->getParams();



        if (!isset($params["product"]["picup"]["picup_shifts"])) {
            $params["product"]["picup"]["picup_shifts"] = null;
        }

        ///Save the data
        $this->saveProductFields($params["product"]["sku"], $params["product"]["picup"]["picup_enabled"], $params["product"]["picup"]["picup_width"], $params["product"]["picup"]["picup_length"], $params["product"]["picup"]["picup_height"], $params["store"], $params["product"]["picup"]["picup_shifts"]);

    }


    public function saveProductFields($productSku, $enabled, $width, $length, $height, $storeId, $shiftData) {
        $shiftData = serialize($shiftData);
        $tableName = $this->_resourceConnection->getTableName('picup_products');
        $connection = $this->_resourceConnection->getConnection();

        $connection->query("delete from {$tableName} where product_sku = '{$productSku}' and store_id = {$storeId}");

        $connection->query("insert into {$tableName} (product_sku, picup_enabled, picup_width, picup_length, picup_height, store_id, shift_data)
                            values ('{$productSku}', {$enabled}, {$width}, {$length}, {$height}, {$storeId}, '{$shiftData}')");
        return true;
    }


}
