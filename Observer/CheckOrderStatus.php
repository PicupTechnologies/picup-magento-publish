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

    protected $_URI_LIVE = 'https://otdcpt-knupprd.onthedot.co.za/picup-api/v1/integration/';
    protected $_URI_TEST = 'https://otdcpt-knupqa.onthedot.co.za/picup-api/v1/integration/';

    protected $_QUOTE_ONE_TO_MANY_LIVE = 'quote/one-to-many';
    protected $_QUOTE_ONE_TO_MANY_TEST = 'quote/one-to-many';

    protected $_ADD_TO_BUCKET_LIVE = 'add-to-bucket';
    protected $_ADD_TO_BUCKET_TEST = 'add-to-bucket';


    protected $_api_key = 'business-a86db193-af45-4022-8070-80b9abb0f115';



    protected $_orderRepository;
    protected $_httpClientFactory;
    protected $_storeManager;

    public $_order;

    public $_customerName;
    public $_CustomerPhone;
    public $_customerAddress;


    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager) {

        $this->_orderRepository = $orderRepository;
        $this->_httpClientFactory = $httpClientFactory;
        $this->_storeManager = $storeManager;

    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $_order = $observer->getEvent()->getOrder();
        $order = $observer->getEvent()->getOrder();
        $customerId = $order->getCustomerId();
        $OrderStatus=$order->getStatus();
        $orderId = $order->getId();
        $shippingMethod = $order->getShippingMethod();
        $shippingDate = date("Y-m-d");

        //Only process shipping request if order is processing (has been paid)
        if ($OrderStatus == 'processing') {

            $this->debugLog("\n ****** PROCESSING *****" , $order->getStatus());

            //Only execute code for Picup Delivery Shifts - This will create or add the order to a bucket
            //        'PicUp Shipping - 2020-09-05 - Saturday Delivery'
            $this->debugLog("Shipping Description", substr($order->getShippingDescription(),0, 14));

            if (substr($order->getShippingDescription(),0, 14) == 'PicUp Shipping'){

                $shippingDate = substr($order->getShippingDescription(),17, 10);
                $this->debugLog("SHIPPING DATE", $shippingDate);


                $this->debugLog("Customer Address11", $order->getShippingAddress()->getData());
                $custAddress = $order->getShippingAddress()->getData();

                $this->_customerAddress = $custAddress['street'] . ', ' . $custAddress['city'] . ', ' . $custAddress['postcode'];
                $this->_customerName = $order->getCustomerName();
                $this->_customerPhone = $order->getShippingAddress()->getTelephone();

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


                $shDesc = $order->getShippingDescription();
                $this->debugLog("This Shipping Description", $shDesc);


                $shiftResults = $this->calculateBucketDate("picup_Monday Delivery");
                $shiftForDelivery = $shiftResults[0];

                $this->debugLog('$collectionDate', $collectionDate);

                //$deliveryDate = $collectionDate;
                $shiftStart = $shiftForDelivery['shift_start'];
                $shiftEnd =  $shiftForDelivery['shift_end'];
                $warehouseId =$shiftForDelivery['warehouse_id'];


                $this->debugLog('elivery_day', $shiftForDelivery['delivery_day']);
                $this->debugLog('$currentWeekDay', $currentWeekDay);


                $this->debugLog('shiftForDelibery', $shiftForDelivery);
                $this->debugLog('$collectionDate', $collectionDate);
                $this->debugLog('$shiftStart', $shiftStart);
                $this->debugLog('$warehouseId', $warehouseId);

                $collectionDate = date_create_from_format("Y-m-d",$shippingDate);
                $this->debugLog('$collectionDate', $collectionDate);

                $this->postShippingBucket(date_format($collectionDate,"c"), $shiftStart,$shiftEnd, $warehouseId);

                $this->debugLog("AFTER POSTSHUIPPING BUCKERT", 'XXXXXXXXXXXXX');
                //die;
            }
        }
    }

    public function debugLog ($name = "Debug Msg", $obj){

        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/picup_response.txt", "\n" . date("Y-m-d h:i:s") . "<<DBG>>" . $name . " <VAL> " . print_r($obj, 1) . " \n", FILE_APPEND);

    }

    ///
    /// Calculate the next available shipping bucket date from the shipping shifts table
    ///
    /// Parameter: SShift Description
    public function calculateBucketDate($desc){




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

        //$this->debugLog("store id", $this->_storeId);

        //Select Data from table
        //$sql = "Select * FROM " . $tableName . " WHERE store_id = " . $this->_storeId;

        $sql = "Select * FROM " . $tableName . " WHERE description = '" . str_replace('picup_','',$desc) . "'";
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
        $postUrl = $this->_URI_TEST.$this->_ADD_TO_BUCKET_LIVE;
        $this->debugLog("bucket Post URL", $postUrl);
        $bucketJSON = $this->buildBucketJson($collectionDate, $shiftStart,$shiftEnd, $warehouseId);
        $bucketResponse =  $this->postJSONRequest($postUrl, $bucketJSON);
        $this->debugLog("Bucket Response", json_decode($bucketResponse));
    }

    public function buildBucketJson($dDate, $sstart, $sEnd, $wId)
    {

        $this->debugLog("dDate", $dDate);
        $this->debugLog("sstart", $sstart);
        $this->debugLog("sEnd", $sEnd);
        $this->debugLog("wId", $wId);

        $this->debugLog("Customer Name",  $this->_customerAddress);
        $this->debugLog("Customer Phone",   $this->_customerAddress);
        $this->debugLog("Customer Address",  $this->_customerAddress);


        // = $this->_storeManager->getStore($request->getStoreId());

        $strBucketJSON = (object)[
            "bucket_details" => (object)[
                "delivery_date" => $dDate,
                "shift_start" => $sstart,
                "shift_end" => $sEnd,
                "warehouse_id" => $wId
            ],
            "shipments" => [
                (object) [
                    "consignment" => "First Suburb",
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
                            "email_address" => null,
                            "special_instructions" => "Not Specified"
                        ],
                    "parcels" =>
                        [
                            (object)[
                                "size" => "parcel-medium",
                                "tracking_number" => "777-888-999",
                                "parcel_reference" => "Parcel Number 1",
                                "description" => "This is the first parcel"]
                        ]

                ] //shipment object

            ] //shipments

        ];

        $this->debugLog("BUILD JSON", $strBucketJSON);

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
        $sql = "Select * FROM " . $tableName . " WHERE store_id = " . $this->_storeId;
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
    function postJSONRequest($postUrl, $json){

        $this->debugLog("\npostJSONRequest", $postUrl);

        $this->debugLog('api key', $this->_api_key);

        $this->debugLog("\npostJSONRequest", $json);

        $client = $this->_httpClientFactory->create();

        $client->setUri($postUrl);
        $client->setRawData(utf8_encode($json));
        $client->setMethod(\Zend_Http_Client::POST);
        $client->setHeaders('api-key', $this->_api_key);
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
