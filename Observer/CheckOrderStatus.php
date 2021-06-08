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

    protected $_URI_LIVE = 'https://picupafricawebapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST = 'https://picupstaging-webapi.azurewebsites.net/v1/integration/';

    protected $_URI_LIVE_AFRICA = 'https://beta.picup.africa/v1/integration/';
    protected $_URI_TEST_AFRICA = 'https://beta.picup.africa/v1/integration/';

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

        $this->debugLog("Config Data for {$field}", $result[0]["value"]);

        return $result[0]["value"];
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer) {
        $this->_order = $observer->getEvent()->getOrder();
        $orderStatus=$this->_order->getStatus();


        //Only process shipping request if order is processing (has been paid)
        if ($orderStatus == 'processing') {

            $this->debugLog("\n ****** PROCESSING *****" , $this->_order->getStatus());

            //Only execute code for Picup Delivery Shifts - This will create or add the order to a bucket
            //        'PicUp Shipping - 2020-09-05 - Saturday Delivery'
            $this->debugLog("Shipping Description", substr($this->_order->getShippingDescription(),0, 14));

            if (substr($this->_order->getShippingDescription(),0, 14) == 'PicUp Shipping'){

                $shippingDate = substr($this->_order->getShippingDescription(),17, 10);
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
                    $collectionDate = $this->next_business_day(date('c'));
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


                $this->debugLog('Delivery day', $shiftForDelivery['delivery_day']);
                $this->debugLog('$currentWeekDay', $currentWeekDay);


                $this->debugLog('shiftForDelibery', $shiftForDelivery);
                $this->debugLog('$collectionDate', $collectionDate);
                $this->debugLog('$shiftStart', $shiftStart);
                $this->debugLog('$warehouseId', $warehouseId);

                $collectionDate = date_create_from_format("Y-m-d",$shippingDate);
                $this->debugLog('$collectionDate', $collectionDate);

                $this->postShippingBucket(date_format($collectionDate,"c"), $shiftStart,$shiftEnd, $warehouseId);

                $this->debugLog("AFTER POST SHIPPING BUCKET", 'XXXXXXXXXXXXX');
                //die;
            }
        }
    }

    public function debugLog ($name = "Debug Msg", $obj){

        //file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/picup_response.txt", "\n" . date("Y-m-d h:i:s") . "<<DBG>>" . $name . " <VAL> " . print_r($obj, 1) . " \n", FILE_APPEND);
        if ($this->getConfigData("debug")) {
            $this->_logger->debug($name, ["context" => print_r($obj)]);
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
        $this->debugLog("Record Count", count($result));


        return $result;
    }



    ///
    /// Process Shipping bucket
    ///
    /// Parameters:
    ///

    public function postShippingBucket($collectionDate, $shiftStart,$shiftEnd, $warehouseId)
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

        $bucketJSON = $this->buildBucketJson($collectionDate, $shiftStart,$shiftEnd, $warehouseId);

        $bucketResponse =  $this->postJSONRequest($postUrl, $bucketJSON);

        $this->debugLog("Bucket Response", json_decode($bucketResponse));
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
                    "reference" => "quote-ref-".$this->_order->getId(),
                    "description" => $item->getProduct()->getName(),
                    "tracking_number" =>$this->_order->getId()
                ];
        }

        $this->debugLog("Parcels",  $parcels);
        return $parcels;
    }

    public function buildBucketJson($dDate, $sStart, $sEnd, $wId)
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
                    "business_reference" => "PICUP BUCKET" . date("Ymdhis"),
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



       // die (print_r ($strBucketJSON,1));


        $this->debugLog("BUILD JSON", json_encode($strBucketJSON));

        return json_encode($strBucketJSON, true);
    }

    ///
    /// Read the picup_warehouse_shifts table to determine the next available delivery shifts for display in the quotes screen
    /// Weekday ID
    ///
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


    ///
    /// Posts the JSON Request
    ///
    /// Parameters : $postUrl = The complete post url ()
    ///              $json = the body of the post request
    ///   returns the body of the post response
    ///


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

        $this->debugLog('JSON Response', json_decode($response));
        return $response;
    }

    public function next_business_day($date) {
        $add_day = 0;
        do {
            $add_day++;
            $new_date = date('Y-m-d', strtotime("$date +$add_day Days"));
            $new_day_of_week = date('w', strtotime($new_date));
        } while($new_day_of_week == 6 || $new_day_of_week == 0);

        return $new_date;
    }

}
