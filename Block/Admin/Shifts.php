<?php



namespace Picup\Shipping\Block\Admin;

use Magento\Framework\View\Element\Template;

class Shifts extends \Magento\Framework\View\Element\Template
{
    protected $_storeManager;
    protected $_resourceConnection;
    protected $_objectManager;
    protected $_formKey;

    protected $_request;


    /**
     * Shifts constructor.
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\App\Request\Http $request,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_storeManager = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $this->_resourceConnection = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
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
     * @return \Magento\Framework\App\Request\Http
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
        $sql = "Select * FROM " . $tableName . " WHERE store_id = " . $storeId;
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
     * @return bool
     */
    public function addShift($storeId, $weekDay, $description, $timeFrom, $timeTo, $price, $cutOffTime) {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("insert into {$tableName} (store_id, delivery_day, shift_start, shift_end, description, price, cutoff_time) values ({$storeId}, {$weekDay}, '{$timeFrom}', '{$timeTo}', '{$description}', {$price}, '{$cutOffTime}')");
        return true;
    }

    /**
     * Deletes a shift based on the id
     * @param $shiftId
     * @return bool
     */
    public function deleteShift($shiftId) {
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $connection = $this->_resourceConnection->getConnection();
        $connection->query("delete from {$tableName} where id = {$shiftId}");
        return true;
    }


}
