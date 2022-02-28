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

class CheckOrderStatus implements ObserverInterface {

    protected $_URI_LIVE = 'https://picupafrica-webapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST = 'https://picupstaging-webapi.azurewebsites.net/v1/integration/';

    protected $_URI_LIVE_AFRICA = 'https://picupafrica-webapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST_AFRICA = 'https://picupafrica-webapi.azurewebsites.net/v1/integration/';

    protected $_ADD_TO_BUCKET_LIVE = 'add-to-bucket';
    protected $_ADD_TO_BUCKET_TEST = 'add-to-bucket';

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;
    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $_httpClientFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;


    public $_order;

    public $_customerName;
    public $_customerPhone;
    public $_customerAddress;
    public $_customerEmail;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger) {

        $this->_orderRepository = $orderRepository;
        $this->_httpClientFactory = $httpClientFactory;
        $this->_storeManager = $storeManager;
        $this->_logger = $logger;
    }

    /**
     * Gets the config data for the picup module
     * @return mixed
     */
    public function getConfigData($field) {
        //carriers/picup/warehouseId
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('core_config_data');

        $sql = "select * from " . $tableName . " where path = 'carriers/picup/{$field}'";

        $result = $connection->fetchAll($sql); // gives associated array, table fields as key in array.

        if (!empty($result)) {
            return $result[0]["value"];
        } else {
            return false;
        }
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer) {
        $this->_order = $observer->getEvent()->getOrder();
        $orderStatus = $this->_order->getStatus();


        //Only process shipping request if order is processing (has been paid)
        if ($orderStatus == 'processing') {

            $this->debugLog("\n ****** PROCESSING *****" , $this->_order->getStatus());

            //Only execute code for Picup Delivery Shifts - This will create or add the order to a bucket
            //        'PicUp Shipping - 2020-09-05 - Saturday Delivery'
            $this->debugLog("Shipping Method", substr($this->_order->getShippingMethod(),0, 14));
			
            if (substr($this->_order->getShippingMethod(),0, 5) == 'picup'){

                $shippingDate = substr($this->_order->getShippingDescription(),17, 10);
                if (!strtotime($shippingDate)) {
                     $shippingDate = $this->NextBusinessDay(date('c'));
                }
                $this->debugLog("SHIPPING DATE", $shippingDate);


                $this->debugLog("Customer Address", $this->_order->getShippingAddress()->getData());
                $custAddress = $this->_order->getShippingAddress()->getData();

                $this->_customerAddress = $custAddress['street'] . ', ' . $custAddress['city'] . ', ' . $custAddress['postcode'];
                $this->_customerName = $this->_order->getCustomerName();
                $this->_customerPhone = $this->_order->getShippingAddress()->getTelephone();
                $this->_customerEmail = $this->_order->getCustomerEmail();

                $this->debugLog("Customer Address", $this->_customerAddress);
                $this->debugLog("Customer Name", $this->_customerName);
                $this->debugLog("Customer Phone", $this->_customerPhone);


                $weekDays = [
                    "Monday" => 1,
                    "Tuesday" => 2,
                    "Wednesday" => 3,
                    "Thursday" => 4,
                    "Friday" => 5,
                    "Saturday" => 6,
                    "Sunday" =>7
                ];

                $currentWeekDay = $weekDays[date("l")];

                $collectionDate = date('c');
                $collectTomorrow = false;

                //if the current server time is less then 14:00 (12:00 local time SA is 2 hours ahead of UTC)
                if (date('H') > 14) {
                    $collectionDate = $this->NextBusinessDay(date('c'));
                    $currentWeekDay++;
                    if ($currentWeekDay > 7){
                        $currentWeekDay = 1;
                    }
                    $collectTomorrow = true;
                }
                else
                {
                    $collectionDate = date('c');
                }

                $this->debugLog("Current Weekday", $currentWeekDay);


                $shDesc = explode ("-",  $this->_order->getShippingDescription());

                $this->debugLog("This Shipping Description", $this->_order->getShippingDescription());


                $shiftResults = $this->calculateBucketDate(str_replace(".", "", trim($shDesc[count($shDesc)-1])));
                $shiftForDelivery = $shiftResults[0];

                $this->debugLog('$collectionDate', $collectionDate);

                //$deliveryDate = $collectionDate;

                $this->debugLog('Shift Data', $shiftForDelivery);

                $shiftStart = $shiftForDelivery['shift_start'];
                $shiftEnd =   $shiftForDelivery['shift_end'];
                $warehouseId = $this->getConfigData('warehouseId');


                $this->debugLog('$currentWeekDay', $currentWeekDay);


                $this->debugLog('shiftForDelibery', $shiftForDelivery);
                $this->debugLog('$collectionDate', $collectionDate);
                $this->debugLog('$shiftStart', $shiftStart);
                $this->debugLog('$warehouseId', $warehouseId);

                $collectionDate = date_create_from_format("Y-m-d",$shippingDate);


                //$this->debugLog('$collectionDate', $collectionDate);

                //get the consignment id based on the shipping method.
                if (!empty($shiftForDelivery["consignment_id"])) {
                    $consignment = $shiftForDelivery["consignment_id"];
                } else {
                    $consignment = $shiftForDelivery["description"];
                }



                $result = $this->postShippingBucket(date_format($collectionDate,"c"), $shiftStart,$shiftEnd, $warehouseId,$consignment);

                //get the tracking information
                foreach ($result as $key => $keyValue) {
                    $shippingData = array(
                        'carrier_code' => "Picup Shipping",
                        'title' => 'Picup Tracking Code',
                        'number' => $keyValue[0]
                    );
                    if ($this->getConfigData('testMode')) {
                        $suffixUrl = "https://staging.picup.co.za/order-tracking";
                    } else {
                        $suffixUrl = "https://picup.co.za/order-tracking";
                    }
                    $this->_order->addStatusHistoryComment("Track order : <a target=\"_blank\" href=\"{$suffixUrl}?waybill=" . $keyValue[0] . "\">{$keyValue[0]}</a>");
                }


                if ($this->getConfigData('automatedShipping')) {
                    //Start of shipment
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
                    $shipment = $convertOrder->toShipment($this->_order);

                    // Loop through order items
                    foreach ($this->_order->getAllItems() as $orderItem) {
                        // Check if order item has qty to ship or is virtual
                        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }

                        $qtyShipped = $orderItem->getQtyToShip();

                        // Create shipment item with qty
                        $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                        // Add shipment item to shipment
                        $shipment->addItem($shipmentItem);
                    }

                    // Register shipment
                    $shipment->register();
                    $this->_order->setIsInProcess(true);

                    $track = $objectManager->create('Magento\Sales\Model\Order\Shipment\TrackFactory')->create()->addData($shippingData);
                    $shipment->addTrack($track)->save();
                    $shipment->save();
                }
                //End of shipment


                $this->_order->save();

                $this->debugLog("AFTER POST SHIPPING BUCKET", $result);
            }
        }
    }




    /**
     * Adds debugging information to the log file
     * @param string $name
     * @param array $obj
     */
    public function debugLog ($name = "Debug Msg", $obj=[]){

        if ($this->getConfigData("debug")) {
            $this->_logger->debug($name, ["context" => json_encode($obj)]);
        }
    }

    /**
     * Gets the bucket data
     * @param $description
     * @return mixed
     */
    public function calculateBucketDate($description)
    {

        $weekDays = [
            "Monday" => 1,
            "Tuesday" => 2,
            "Wednesday" => 3,
            "Thursday" => 4,
            "Friday" => 5,
            "Saturday" => 6,
            "Sunday" =>7
        ];

        $currentWeekDay = $weekDays[date("l")];

        $this->debugLog("Current Weekday", $currentWeekDay);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $tableName = $resource->getTableName('picup_warehouse_shifts');
        $sql = "select * from " . $tableName . " where description = '{$description}'";
        $this->debugLog("Shift SQL", $sql);

        $result = $connection->fetchAll($sql); // gives associated array, table fields as key in array.

        if (empty($result)) {
            $tableName = $resource->getTableName('picup_warehouse_zones');
            $sql = "select * from " . $tableName . " where description = '{$description}'";
            $this->debugLog("Shift SQL", $sql);

            $result = $connection->fetchAll($sql);
        }

        $this->debugLog("Record Count", count($result));

        return $result;
    }



    ///
    /// Process Shipping bucket
    ///
    /// Parameters:
    ///

    public function postShippingBucket($collectionDate, $shiftStart,$shiftEnd, $warehouseId, $consignment)
    {
        $this->debugLog("-----", "INSIDE postShippingBucket");

        if ($this->getConfigData('outsideSouthAfrica')) {
            if ($this->getConfigData('testMode')) {
                $postUrl = $this->_URI_TEST_AFRICA . $this->_ADD_TO_BUCKET_TEST;
            } else {
                $postUrl = $this->_URI_LIVE_AFRICA .$this->_ADD_TO_BUCKET_LIVE;
            }
        } else {
            if ($this->getConfigData('testMode')) {
                $postUrl = $this->_URI_TEST . $this->_ADD_TO_BUCKET_TEST;
            } else {
                $postUrl = $this->_URI_LIVE . $this->_ADD_TO_BUCKET_LIVE;
            }
        }

        $bucketJSON = $this->buildBucketJson($collectionDate, $shiftStart,$shiftEnd, $warehouseId, $consignment);

        $this->debugLog("Bucket Request", $bucketJSON);

        $bucketResponse =  $this->postJSONRequest($postUrl, $bucketJSON);

        $this->debugLog("Bucket Response", json_decode($bucketResponse));

        return json_decode($bucketResponse);
    }

    /**
     * Gets the parcel size and number of items
     * @param RateRequest $request
     * @return array
     */
    function getParcels(): array
    {
        $items = $this->_order->getAllItems();

        $this->debugLog("Items",  $items);
        $parcels = [];

        foreach ($items as $id => $item) {
            $parcels [] =
                (object)[
                    "size" => "parcel-medium",
                    "parcel_reference" => "quote-ref-".$this->_order->getId()."-$id",
                    "description" => $item->getProduct()->getName()
                ];
        }

        $this->debugLog("Parcels",  $parcels);
        return $parcels;
    }

    /**
     * @param $dDate
     * @param $sStart
     * @param $sEnd
     * @param $wId
     * @return false|string
     */
    public function buildBucketJson($dDate, $sStart, $sEnd, $wId, $consignment)
    {

        $this->debugLog("dDate", $dDate);
        $this->debugLog("sstart", $sStart);
        $this->debugLog("sEnd", $sEnd);
        $this->debugLog("wId", $wId);

        $this->debugLog("Customer Name",  $this->_customerAddress);
        $this->debugLog("Customer Phone",   $this->_customerAddress);
        $this->debugLog("Customer Address",  $this->_customerAddress);


        // = $this->_storeManager->getStore($request->getStoreId());

        $strBucketJSON = (object)[
            "bucket_details" => (object)[
                "delivery_date" => $dDate,
                "shift_start" => $sStart,
                "shift_end" => $sEnd,
                "warehouse_id" => $wId
            ],
            "shipments" => [
                (object) [
                    "visit_type" => "delivery",
                    "consignment" => $consignment,
                    "business_reference" => $this->_order->getId(),
                    "address" => (object)[
                        "address_line_1" => null,
                        "address_line_2" => null,
                        "address_line_3" => null,
                        "address_line_4" => null,
                        "formatted_address" => $this->_customerAddress,
                        "latitude" => null,
                        "longitude" => null,
                        "street_or_farm_no" => null,
                        "street_or_farm" => null,
                        "suburb" => null,
                        "city" => null,
                        "country" => null,
                        "postal_code" => null
                    ],

                    "contact" =>

                        (object)[
                            "customer_name" => $this->_customerName,
                            "customer_phone" =>  $this->_customerPhone,
                            "email_address" => $this->_customerEmail,
                            "special_instructions" => "Not Specified"
                        ],
                    "parcels" => $this->getParcels()

                ] //shipment object

            ] //shipments

        ];

        $this->debugLog("BUILD JSON", json_encode($strBucketJSON));

        return json_encode($strBucketJSON, true);
    }

    /**
     * Gets the available warehouse shifts
     * @return mixed
     */
    public function getAvailableShifts(){

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('picup_warehouse_shifts');

        $this->debugLog("store id", $this->_storeId);

        //Select Data from table
        $sql = "select * from". $tableName . " where store_id = " . $this->_storeId;
        $this->debugLog("Shift SQL", $sql);
        $result = $connection->fetchAll($sql); // gives associated array, table fields as key in array.

        $this->debugLog("Record Count", count($result));

        return $result;
    }

    /**
     * @param $postUrl
     * @param $json
     * @return \Zend_Http_Response
     * @throws \Zend_Http_Client_Exception
     */
    function postJSONRequest($postUrl, $json)
    {
        $this->debugLog('POST URL', $postUrl);

        $client = $this->_httpClientFactory->create();
        $client->setUri($postUrl);
        $client->setRawData(utf8_encode($json));
        $client->setMethod(\Zend_Http_Client::POST);

        if ($this->getConfigData('testMode')) {
            $client->setHeaders('api-key', $this->getConfigData("apiKeyTest"));
        } else {
            $client->setHeaders('api-key', $this->getConfigData("apiKey"));
        }
        $client->setHeaders('Content-Type', 'application/json');

        $response = $client->request();

        $this->debugLog('JSON Response', $response->getBody());
        return $response->getBody();
    }

    public function NextBusinessDay($date) {
        $add_day = 0;
        do {
            $add_day++;
            $new_date = date('Y-m-d', strtotime("$date +$add_day Days"));
            $new_day_of_week = date('w', strtotime($new_date));
        } while($new_day_of_week == 6 || $new_day_of_week == 0);

        return $new_date;
    }

}
