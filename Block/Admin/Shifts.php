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

namespace Picup\Shipping\Block\Admin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class Shifts extends \Magento\Framework\View\Element\Template
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var FormKey
     */
    protected $_formKey;

    /**
     * @var Http
     */
    protected $_request;

    /**
     * Shifts constructor.
     * @param Template\Context $context
     * @param FormKey $formKey
     * @param Http $request
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        FormKey $formKey,
        Http $request,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_storeManager = $storeManager;
        $this->_resourceConnection = $resourceConnection;
        $this->_formKey = $formKey;
        $this->_request = $request;
    }

    /**
     * Gets a form key
     * @return mixed
     */
    public function getFormKey() {
        return $this->_formKey->getFormKey();
    }

    /**
     * Gets the http request
     * @return Http
     */
    public function getRequest() {
        return $this->_request;
    }

    /**
     * Gets all the shifts for a store
     * @param $storeId
     * @return mixed
     */
    public function getShifts($storeId) {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $sql = "select * from " . $tableName . " where store_id = " . $storeId;
        $connection = $this->_resourceConnection->getConnection();
        return $connection->fetchAll($sql);
    }

    /**
     * Gets all the shifts for a store
     * @param $storeId
     * @return mixed
     */
    public function getZones($storeId) {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_zones');
        $sql = "select * from " . $tableName . " where store_id = " . $storeId;
        $connection = $this->_resourceConnection->getConnection();
        return $connection->fetchAll($sql);
    }

    /**
     * Gets all the stores
     * @return mixed
     */
    public function getStores() {
        return $this->_storeManager->getStores();
    }

    /**
     * Adds Picup shifts to the database
     * @param $storeId
     * @param $weekDay
     * @param $description
     * @param $timeFrom
     * @param $timeTo
     * @param $price
     * @param $cutOffTime
     * @param $picupZones
     * @param $sameDayCaption
     * @param $nextDayCaption
     * @return bool
     */
    public function addShift($storeId, $weekDay, $description, $timeFrom, $timeTo, $price, $cutOffTime, $picupZones, $sameDayCaption, $nextDayCaption) {
        if (empty($description)) return false;
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("insert into {$tableName} (store_id, delivery_day, shift_start, shift_end, description, price, cutoff_time, picup_zones, same_day_caption, next_day_caption) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$storeId, $weekDay, $timeFrom, $timeTo, $description, $price, $cutOffTime, $picupZones, $sameDayCaption, $nextDayCaption]);
        return true;
    }

    /**
     * @param $storeId
     * @param $weekDay
     * @param $description
     * @param $timeFrom
     * @param $timeTo
     * @param $price
     * @param $cutOffTime
     * @param $picupZones
     * @param $sameDayCaption
     * @param $nextDateCaption
     * @param $shiftId
     * @return bool
     */
    public function updateShift($storeId, $weekDay, $description, $timeFrom, $timeTo, $price, $cutOffTime, $picupZones, $sameDayCaption, $nextDateCaption, $shiftId): bool
    {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("update {$tableName} set store_id = ?, delivery_day =?, shift_start = ?, shift_end = ?, description = ?, price = ?, cutoff_time =?, picup_zones =?, same_day_caption = ?, next_day_caption = ? where id = ?",  [$storeId, $weekDay, $timeFrom, $timeTo, $description, $price, $cutOffTime, $picupZones, $sameDayCaption, $nextDateCaption, $shiftId]);

        return true;
    }


    /**
     * Adds Picup zones to the database
     * @param $storeId
     * @param $description
     * @param $postalCodes
     * @param $timeFrom
     * @param $timeTo
     * @param $price
     * @param $cutOffHours
     * @param $consignmentId
     * @param $showZone
     * @param $postalCodesIgnore
     * @return bool
     */
    public function addZone($storeId, $description, $postalCodes, $timeFrom, $timeTo, $price, $cutOffHours, $consignmentId, $showZone, $postalCodesIgnore): bool
    {
        if (empty($description)) return false;

        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_zones');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("insert into {$tableName} (store_id, postal_codes, shift_start, shift_end, description, price, cutoff_hours, consignment_id, show_zone, postal_codes_ignore) values (?, ?, ?, ?, ? ,? ,? ,?, ?, ?)", [$storeId, $postalCodes, $timeFrom, $timeTo, $description, $price, $cutOffHours, $consignmentId, $showZone, $postalCodesIgnore]);

        return true;
    }

    /**
     * @param $storeId
     * @param $description
     * @param $postalCodes
     * @param $timeFrom
     * @param $timeTo
     * @param $price
     * @param $cutOffHours
     * @param $consignmentId
     * @param $showZone
     * @param $postalCodesIgnore
     * @param $zoneId
     * @return bool
     */
    public function updateZone($storeId, $description, $postalCodes, $timeFrom, $timeTo, $price, $cutOffHours, $consignmentId, $showZone, $postalCodesIgnore, $zoneId): bool
    {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_zones');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("update {$tableName} set store_id = ?, postal_codes = ?, shift_start = ?,  shift_end = ?,  description = ?, price = ?, cutoff_hours = ?, consignment_id = ?, show_zone = ?, postal_codes_ignore = ? where id = ?", [$storeId, $postalCodes, $timeFrom, $timeTo, $description, $price, $cutOffHours, $consignmentId, $showZone, $postalCodesIgnore, $zoneId]);

        return true;
    }

    /**
     * Deletes a shift based on the id
     * @param $shiftId
     * @return bool
     */
    public function deleteShift($shiftId): bool
    {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("delete from {$tableName} where id = {$shiftId}");

        return true;
    }

    /**
     * Deletes a zone based on the id
     * @param $zoneId
     * @return bool
     */
    public function deleteZone($zoneId): bool
    {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_zones');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("delete from {$tableName} where id = {$zoneId}");

        return true;
    }

}

