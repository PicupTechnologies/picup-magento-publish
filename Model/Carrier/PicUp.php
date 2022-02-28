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

namespace Picup\Shipping\Model\Carrier;

use _HumbugBoxe8a38a0636f4\Nette\Utils\DateTime;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;

class PicUp extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements \Magento\Shipping\Model\Carrier\CarrierInterface {

    protected $_URI_LIVE = 'https://picupafricawebapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST = 'https://picupstaging-webapi.azurewebsites.net/v1/integration/';

    protected $_URI_LIVE_AFRICA = 'https://picupafrica-webapi.azurewebsites.net/v1/';
    protected $_URI_TEST_AFRICA = 'https://picupafrica-webapi.azurewebsites.net/v1/';

    protected $_QUOTE_ONE_TO_MANY_LIVE = 'quote/one-to-many';
    protected $_QUOTE_ONE_TO_MANY_TEST = 'quote/one-to-many';

    protected $_ADD_TO_BUCKET_LIVE = 'add-to-bucket';
    protected $_ADD_TO_BUCKET_TEST = 'add-to-bucket';

    protected $_DETAILS_LIVE = '/details';
    protected $_DETAILS_TEST = '/details';

    protected $_code = "picup";
    protected $_storeId = 0;


    /**
     * @var ObjectManager
     */
    protected $_objectManager;

    /**
     * @var ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var  ZendClientFactory
     */
    protected $_httpClientFactory;


    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var Cart
     */
    protected $_cart;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    protected $checkOut;

    /**
     * Picup constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param ZendClientFactory $httpClientFactory
     * @param StoreManagerInterface $storeManager
     * @param Cart $cart
     * @param Session $customerSession ,
     * @param ManagerInterface $messageManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
                                \Psr\Log\LoggerInterface $logger,
                                ResultFactory $rateResultFactory,
                                MethodFactory $rateMethodFactory,
                                ZendClientFactory $httpClientFactory,
                                StoreManagerInterface $storeManager,
                                Cart $cart,
                                Session $customerSession,
                                ManagerInterface $messageManager,
                                \Magento\Checkout\Model\Session $checkoutSession,
                                array $data = [])
    {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_httpClientFactory = $httpClientFactory;
        $this->_storeManager = $storeManager;
        $this->_cart = $cart;
        $this->_customerSession = $customerSession;
        $this->_messageManager = $messageManager;
        $this->_logger = $logger;
        $this->checkOut = $checkoutSession;
        $this->_objectManager = ObjectManager::getInstance();
        $this->resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $this->resource->getConnection();

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }


    /**
     * Collect the Rates
     * @param RateRequest $request
     * @return bool|\Magento\Framework\DataObject|Result|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active') || empty($request->getDestPostcode())) {
            return false;
        }

        try {

            $hasResult = false;

            $store = $this->_storeManager->getStore($request->getStoreId());

            $this->_storeId = $store->getId();
            $this->debugLog("Store ID --", $this->_storeId);

            $result = $this->_rateResultFactory->create();
            $onDemand = $this->getConfigData('enableOnDemand');
            $freeShipping = $this->getConfigData('enableFreeShipping');

            $quote = $this->checkOut->getQuote();


            /// Only get quotes of the OnDemand is active in picup settings
            if ($onDemand == 1 && !empty($request->getDestPostcode())) {
                $this->debugLog("On Demand", $onDemand);
                $json = $this->getQuoteOneToManyJSON($request);
                $quotes = $this->getQuoteOneToMany($json);
                if (!empty($quotes)) {
                    foreach ($quotes as $id => $quote) {

                        $this->debugLog("Carrier Title", $this->getConfigData('name'));

                        //Replace 'vehicle-motorcycle' with 'On Demand: Motorcycle
                        $onDemandDesc = str_replace('vehicle-', 'On Demand: ', $quote->description);

                        $method = $this->_rateMethodFactory->create();
                        $method->setCarrier($this->_code);
                        $method->setCarrierTitle('On Demand - Picup');
                        $method->setMethod($quote->description);
                        $method->setMethodTitle($onDemandDesc);
                        $method->setPrice($quote->price_ex_vat);
                        $method->setCost($quote->price_ex_vat);
                        $result->append($method);
                        $hasResult = true;
                        break; //only quoting on the cheapest quote
                    }
                }
            }


            /// Only get quotes of the OnDemand is active in picup settings
            if ($freeShipping == 1) {
                $freeShippingThreshold = (int)$this->getConfigData('freeShippingThreshold');
                $total = $quote->getGrandTotal();

                if (!empty($freeShippingThreshold) && ($total >= $freeShippingThreshold)) {
                    $method = $this->_rateMethodFactory->create();
                    $method->setCarrier($this->_code);
                    $method->setCarrierTitle('Free Shipping - Picup');
                    $method->setMethod("Picup Free Shipping");
                    $method->setMethodTitle("Free Shipping");
                    $method->setPrice(0);
                    $method->setCost(0);
                    $result->append($method);
                    $hasResult = true;
                }
            }

            /*
             *  Generating Picup Bucket based pricing
             */

            $this->debugLog("\n AVAILABLE ZONES", ">>>>>>> Start >>>>>>>>\n");
            $zones = $this->getAvailableZones();
            $this->debugLog("\n AVAILABLE ZONES", "<<<<<<< End <<<<<<<<<<\n");



            $zonePostalCodes = [];
            $sortedShifts = [];
            $sortedShiftsValid = [];

            //global next day var
            $nextDay = date_create(date("Y-m-d"));
            $nextDay = date_add($nextDay, date_interval_create_from_date_string("1 days"));


            if (!empty($zones)) {
                if (!empty($request->getDestPostcode())) {
                    foreach ($zones as $zid => $zone) {
                        $zonePostalCodes[$zone["description"]] = $zone["postal_codes"];

                        //Skip ignored postal codes
                        if (strpos($zone["postal_codes_ignore"], $request->getDestPostcode()) === true) {
                            continue;
                        }


                        //Cutoff hours need to be subtracted off start time
                        $cutOffTime = new \DateTime(date("Y-m-d") . " " . $zone["shift_start"]);
                        $cutOffTime->sub(new \DateInterval("PT{$zone["cutoff_hours"]}H"));

                        $currentTime = new \DateTime();


                        if ($currentTime < $cutOffTime && $zone["show_zone"] == 1) {
                            //$zonePostalCodes[$zone["description"]] = $zone["postal_codes"];
                            $this->debugLog("\nZone Description", $zone["description"] . " " . $cutOffTime->format("Y-m-d H:i:s"));
                            if (strpos($zone["postal_codes"], $request->getDestPostcode()) !== false || trim($zone["postal_codes"]) == "") {
                                //same day
                                $finalDelDate = date("Y-m-d");
                                if (strpos($zone["description"], "{date}") !== false) {
                                    $zone["description"] = str_replace('{date}', date("Y-m-d"), $zone["description"]);
                                }

                                //next day
                                if (strpos($zone["description"], "{date+1}") !== false) {
                                    $finalDelDate = date_format($nextDay,"Y-m-d");
                                    $zone["description"] = str_replace('{date+1}', date_format($nextDay,"Y-m-d"), $zone["description"]);
                                }

                                $method2 = $this->_rateMethodFactory->create();
                                $method2->setCarrier($this->_code);
                                $method2->setCarrierTitle($this->getConfigData('name'));
                                $method2->setMethod($zone["description"]);
                                $method2->setMethodTitle($zone["description"]);
                                $method2->setPrice($zone["price"]);
                                $method2->setCost($zone["price"]);

                                $sortedShifts[$finalDelDate] = $method2;
                                $sortedShiftsValid[$finalDelDate] = "o";

                            }
                        }
                    }
                } else {
                    foreach ($zones as $zid => $zone) {
                        $zonePostalCodes[$zone["description"]] = $zone["postal_codes"];
                    }
                }
            }


            $this->debugLog("\n AVAILABLE SHIFTS", ">>>>>>> Start >>>>>>>>\n");
            $shifts = $this->getAvailableShifts();
            $this->debugLog("\n AVAILABLE SHIFTS", "<<<<<<< End <<<<<<<<<<\n");

            $weekDays = [
                "Monday" => 1,
                "Tuesday" => 2,
                "Wednesday" => 3,
                "Thursday" => 4,
                "Friday" => 5,
                "Saturday" => 6,
                "Sunday" => 7
            ];

            $currentDayInt = $weekDays[date("l")];

            if (!empty($shifts)) {

                foreach ($shifts as $sid => $shift) {
                    $this->debugLog("\nShift Description", $shift["description"]);

                    $dDayInt = $shift["delivery_day"]; //day of delivery
                    $diffDaysInt = $dDayInt - $currentDayInt;

                    if ($diffDaysInt < 0) {
                        $diffDaysInt = $diffDaysInt + 7;
                    }

                    $this->debugLog("\nDays Difference", $diffDaysInt);


                    $finalDelDate = strtotime("+$diffDaysInt" . " days", strtotime(date("Y/m/d")));

                    $delDateString = date("Y-m-d", $finalDelDate);
                    $descString = $delDateString . " - " . $shift["description"];
                    $this->debugLog("\nFormatted Shift Description", $descString);


                    $this->debugLog("\nShift Description", $shift["description"] . " on $finalDelDate.");

                    //See if we have a zone applied and used the postal codes for that zone
                    $picupZones = explode(",", $shift["picup_zones"]);

                    $this->debugLog("Zones", $picupZones);

                    $canAdd = true;
                    if (!empty($picupZones)) {
                        $zipCodes = "";
                        foreach ($picupZones as $pid => $picupZone) {
                            if (!empty($picupZone)) {
                                if (isset($zonePostalCodes[trim($picupZone)])) {
                                    $zipCodes .= $zonePostalCodes[trim($picupZone)];
                                }
                            }
                        }

                        if (strpos($zipCodes, $request->getDestPostcode()) === false && trim($zipCodes) !== "") {
                            $canAdd = false;
                        }
                    }


                    $deliveryDate = new \DateTime($delDateString);
                    $endShiftTime = new \DateTime(date("Y-m-d") . " " . $shift["shift_end"]);

                    if ($canAdd) {
                        //Check if same day for cut off time
                        if ($deliveryDate->format("Y-m-d") == $endShiftTime->format("Y-m-d")) {
                            $currentTime = new \DateTime();

                            try {
                                $cutOffTime = new \DateTime(date("Y-m-d") . " " . $shift["shift_start"]);
                                $cutOffTime->sub(new \DateInterval("PT{$shift["cutoff_time"]}H"));
                            } catch (\Exception $exception) {
                                $canAdd = false;
                            }

                            if ($currentTime < $cutOffTime) {
                                $finalDelDate = date("Y-m-d");
                            } else {
                                $finalDelDate = strtotime("+7 days", strtotime(date("Y-m-d")));
                                $delDateString = date("Y-m-d", $finalDelDate);
                                $descString = $delDateString . " - " . $shift["description"];
                                $canAdd = true;
                            }
                        }

                        if ($delDateString === date_format($currentTime, "Y-m-d")) {
                            $descString = $shift["same_day_caption"];
                        }

                        if ($delDateString === date_format($nextDay, "Y-m-d")) {
                            $descString = $shift["next_day_caption"];
                        }

                        if ($canAdd) {
                            $method1 = $this->_rateMethodFactory->create();
                            $method1->setCarrier($this->_code);
                            $method1->setCarrierTitle($this->getConfigData('name'));
                            $method1->setMethod($shift["description"]);
                            $method1->setMethodTitle($descString);
                            $method1->setPrice($shift["price"]);
                            $method1->setCost($shift["price"]);


                            $sortedShifts[$delDateString] = $method1;
                            $sortedShiftsValid[$finalDelDate] = "o";
                        }
                    }
                }

               //array_unique($sortedShifts);

                ksort($sortedShifts);
                ksort($sortedShiftsValid);

                foreach ($sortedShifts as $sortedShift) {
                    $result->append($sortedShift);
                    $hasResult = true;
                }
            }

            if (!$hasResult) return false;

            return $result;
        }  catch(\Exception $exception) {
            $this->debugLog("Exception", $exception->getMessage());
            return false;
        }
    }

    /**
     * Add number of days to a date
     * @param $date
     * @param $days
     * @return false|string
     */
    public function addDayswithdate($date,$days)
    {
        $date = strtotime("+".$days." days", strtotime($date));
        return  date("Y-m-d", $date);
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        return ['picup' => $this->getConfigData('name')];
    }

    /**
     * Is Tracking available
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Gets a Quote based on API - https://integrate.picup.co.za/?version=latest
     * @param RateRequest $request
     * @return false|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getQuoteOneToManyJSON(RateRequest $request)
    {
        $destCountry = "South Africa"; //Need to see what to do about this when not south africa

        $customerName = "Guest";
        $customerEmail = "info@picup.co.za";
        $customerPhone = "0111001000";

        if($this->_customerSession->isLoggedIn()){
            $customerName = $this->_customerSession->getCustomer()->getName();
            $customerEmail =  $this->_customerSession->getCustomer()->getEmail();
            $customerPhone = $this->_customerSession->getCustomer()->getDefaultShippingAddress()->getTelephone();
            $customerAddress = $this->_customerSession->getCustomer()->getDefaultShippingAddress()->getData();

            $destStreet = $customerAddress['street'];
            $destCity = $customerAddress['city'];
            $destPostCode = $customerAddress['postcode'];
        }
        else
        {
            $destStreet = $request->getDestStreet();
            $destCity = $request->getDestCity();
            $destPostCode = $request->getDestPostcode();
        }

        $store = $this->_storeManager->getStore($request->getStoreId());

        $this->_storeId = $store->getId();
        $this->debugLog("Store ID --", $this->_storeId);

        $JSON = (object)[
            "customer_ref" => $store->getName()." PICUP QUOTE" . date("Ymdhis"),
            "is_for_contract_driver" => false,
            "scheduled_date" => $this->getCollectionDate(true),
            "courier_costing" => "COL",
            "sender" => (object)[
                "address" => (object) [
                    "unit_no" => null,
                    "complex" => null,
                    "street_or_farm_no" => null,
                    "street_or_farm" => htmlspecialchars((string) $destStreet),
                    "suburb" => null,
                    "city" => htmlspecialchars((string) $destCity),
                    "postal_code" => htmlspecialchars((string) $destPostCode),
                    "country" => htmlspecialchars($destCountry)
                ],
                "contact" => (object)[
                    "name"=> $customerName,
                    "email"=> $customerEmail,
                    "cellphone" => $customerPhone
                ]
            ],
            "receivers" => [
                (object)[
                    "address" => (object) [
                        "unit_no" => null,
                        "complex" => null,
                        "street_or_farm_no" => null,
                        "street_or_farm" => htmlspecialchars((string) $destStreet),
                        "suburb" => null,
                        "city" => htmlspecialchars((string) $destCity),
                        "postal_code" => htmlspecialchars((string) $destPostCode),
                        "country" => htmlspecialchars($destCountry)
                    ],
                    "contact" => (object)[
                        "name"=> $customerName,
                        "email"=> $customerEmail,
                        "cellphone" => $customerPhone
                    ],
                    "special_instructions"=> "",
                    "parcels" => $this->getParcels($request)
                ]
            ],
            "optimize_waypoints" => true
        ];

        return json_encode($JSON, true);
    }


    /**
     * Gets the current warehouse id linked to the store
     * @return mixed
     */
    protected function getWarehouseId() : string
    {
        if (!empty($this->getConfigData('warehouseId'))) {
            return $this->getConfigData('warehouseId');
        } else {
            $this->_messageManager->addErrorMessage("Please make sure your warehouses are configured on Picup Shipping module");
        }
        return "";
    }


    /**
     * Calculates the next available shipping date
     * @param false $isQuoteRequest
     * @return string
     */
    protected function getCollectionDate($isQuoteRequest = false): string
    {
        $collectionDate = date("Y-m-d H:i:s");
        $this->debugLog("collectionDate", $collectionDate);

        //if the current server time is less then 14:00 (12:00 local time SA is 2 hours ahead of UTC)
        if (date('H') > 14) {
            $collectionDate = $this->next_business_day(date('c'));
        }
        else
        {
            $collectionDate = date('c');
        }

        return $isQuoteRequest ?  date('Y-m-d',strtotime($collectionDate)) . "T" . date('H:i:s',strtotime('+2 hour +5 minutes',strtotime($collectionDate))) . "+02:00": $collectionDate->format('Ymd');
    }


    /**
     * Calculates the next business day
     * @param $date
     * @return false|string
     */
    function next_business_day($date) {
        $add_day = 0;

        do {
            $add_day++;
            $new_date = date('Y-m-d', strtotime("$date +$add_day Days"));
            $new_day_of_week = date('w', strtotime($new_date));
        } while($new_day_of_week == 6 || $new_day_of_week == 0);

        return $new_date;
    }

    /**
     * Gets the parcel size and number of items
     * @param RateRequest $request
     * @return array
     */
    function getParcels(RateRequest $request): array
    {
        $items = $request->getAllItems();
        $parcels = [];

        foreach ($items as $id => $item) {
            $parcels [] =
                (object)[
                    "size" => "parcel-medium",
                    "reference" => "quote-ref-".$id,
                    "description" => $item->getProduct()->getName(),
                    "tracking_number" => "quote-ref-".$id
                ];
        }

        return $parcels;
    }

    /**
     * Processes Picup Quotes for One to Many
     * @param $json
     * @return |null
     */
    function getQuoteOneToMany ($json) {
        if ($this->getConfigData('outsideSouthAfrica')) {
            if ($this->getConfigData('testMode')) {
                $url = $this->_URI_TEST_AFRICA . $this->_QUOTE_ONE_TO_MANY_TEST;
            } else {
                $url = $this->_URI_LIVE_AFRICA . $this->_QUOTE_ONE_TO_MANY_LIVE;
            }
        } else {
            if ($this->getConfigData('testMode')) {
                $url = $this->_URI_TEST .$this->_QUOTE_ONE_TO_MANY_TEST;
            } else {
                $url = $this->_URI_LIVE . $this->_QUOTE_ONE_TO_MANY_LIVE;
            }
        }

        try {
            $response = $this->postJSONRequest($url, $json);

            $this->debugLog("RESPONSE ONE TO MANY", $response);

            $quotes = json_decode($response->getBody());
        } catch (\Exception $exception) {
            $quotes = [];
        }

        if (isset($quotes->picup) && !empty($quotes->picup)) {

            //returning only the first/cheapest quote from options provided by picup as per meeting on 2020-08-05
            return $quotes->picup->service_types;

        } else {
            return null;
        }
    }


    /**
     * Posts the JSON request
     * @param $postUrl
     * @param $json
     * @return mixed
     */
    function postJSONRequest($postUrl, $json){

        $this->debugLog("JSON", $json);

        $this->debugLog("\nPost URL", $postUrl);

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

        $this->debugLog("response", $response);

        return $response;
    }

    /**
     * Logs debug messages to picup_response.txt file
     * @param string $name
     * @param $obj
     */
    public function debugLog ($name = "Debug Msg", $obj=[]){
        if ($this->getConfigData("debug")) {
            $this->_logger->debug($name, ["context" => print_r ($obj, 1)]);
        }
    }

    /**
     * Check the tables for the next shift
     * @return mixed
     */
    public function getAvailableShifts()
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
        $tableName = $this->resource->getTableName('picup_warehouse_shifts');
        $this->debugLog("store id", $this->_storeId);
        //Select Data from table
        $sql = "Select * FROM " . $tableName . " WHERE store_id = " . $this->_storeId;
        $this->debugLog("Shift SQL", $sql);
        $connection = $this->resource->getConnection();
        $result = $this->connection->fetchAll($sql); // gives associated array, table fields as key in array.
        $this->debugLog("Record Count", count($result));

        return $result;
    }


    /**
     * Check the tables for the next zone
     * @return mixed
     */
    public function getAvailableZones()
    {
        $tableName = $this->resource->getTableName('picup_warehouse_zones');
        $this->debugLog("zones for store id", $this->_storeId);

        $sql = "Select * FROM " . $tableName . " WHERE store_id = " . $this->_storeId;
        $this->debugLog("Zone SQL", $sql);

        $connection = $this->resource->getConnection();
        $result = $connection->fetchAll($sql); // gives associated array, table fields as key in array.

        $this->debugLog("Record Count", count($result));

        return $result;
    }
}

