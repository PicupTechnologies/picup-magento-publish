<?php
namespace Picup\Shipping\Model\Carrier;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
class PicUp extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements \Magento\Shipping\Model\Carrier\CarrierInterface {

    protected $_URI_LIVE = 'https://picupprod-webapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST = 'https://picupstaging-webapi.azurewebsites.net/v1/integration/';

    protected $_QUOTE_ONE_TO_MANY_LIVE = 'quote/one-to-many';
    protected $_QUOTE_ONE_TO_MANY_TEST = 'quote/one-to-many';

    protected $_ADD_TO_BUCKET_LIVE = 'add-to-bucket';
    protected $_ADD_TO_BUCKET_TEST = 'add-to-bucket';

    protected $_DETAILS_LIVE = '/details';
    protected $_DETAILS_TEST = '/details';

    protected $_code = "picup";
    protected $_storeId = 0;


    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $_objectManager;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var  \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $_httpClientFactory;


    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $_cart;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Picup constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Customer\Model\Session $customerSession,
     * @param array $data
     */

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
                                \Psr\Log\LoggerInterface $logger,
                                \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
                                \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
                                \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Checkout\Model\Cart $cart,
                                \Magento\Customer\Model\Session $customerSession,
                                \Magento\Framework\Message\ManagerInterface $messageManager,

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
        if (!$this->getConfigFlag('active')) {
            return false;
        }


        $result = $this->_rateResultFactory->create();

        $json = $this->getQuoteOneToManyJSON($request);

        $quotes = $this->getQuoteOneToMany ($json);

        $onDemand = $this->getConfigData('enableOnDemand');
        $this->debugLog("On Demand", $onDemand);


        /// Only get quotes of the OnDemand is active in picup settings
        if ($onDemand == 1){
            if(!empty($quotes)) {
                foreach ($quotes as $id => $quote) {

                    $this->debugLog("Carrier Title", $this->getConfigData('name'));
                    $moduleCode = $this->_code;

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
                    break; //only quoting on the cheapest quote
                }
            }
        }

        /*
         *  Generating Picup Bucket based pricing
         */

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
            "Sunday" =>7
        ];
        $currentDayInt =  $weekDays[date("l")];

        foreach($shifts as $id => $shift){

            $this->debugLog("\nShift Description", $shift["description"]);

            $dDayInt =  $shift["delivery_day"];
            $diffDaysInt = $dDayInt - $currentDayInt;

            if ($diffDaysInt < 0){
                $diffDaysInt = $diffDaysInt + 7;
            }
            $this->debugLog("\nDays Difference", $diffDaysInt);

            $finalDelDate = strtotime("+$diffDaysInt"." days", strtotime(date("Y/m/d")));
            $delDateString = date("Y-m-d", $finalDelDate);
            $descString = $delDateString . " - " . $shift["description"] . ". ";
            $this->debugLog("\nFormatted Shift Description", $descString);

            $this->debugLog("\nShift Description", $shift["description"] . " on $finalDelDate.");

            $method1 = $this->_rateMethodFactory->create();
            $method1->setCarrier($this->_code);
            $method1->setCarrierTitle($this->getConfigData('name'));
            $method1->setMethod($shift["description"]);
            $method1->setMethodTitle($descString);
            $method1->setPrice($shift["price"]);
            $method1->setCost($shift["price"]);
            $result->append($method1);
        }

        /*
        $this->debugLog("-----", "Calling Bucket Code");
        $this->postShippingBucket();
        $this->debugLog("-----", "Ending Bucket Code");
        */

        return $result;
    }

    /**
     * Add number of days to a date
     * @param $date
     * @param $days
     * @return false|string
     */
    public function addDayswithdate($date,$days){

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

        $rawRequest = $request;

        // customer info fields required for post message of quote
        // determine if logged in, and use logged in user details or
        // get the fields captured on the checkout screen

        $destStreet = "";
        $destCity = "";
        $destPostCode = "";
        $destCountry = "";

        $isLoggedIn = false;

        $customerName = "Somebody";
        $customerEmail = "andrevanzuydam@gmail.com";
        $customerPhone = "0111001000";




        if($this->_customerSession->isLoggedIn()){
            //Logged In : Get Default Address

            $customerName = $this->_customerSession->getCustomer()->getName();
            $customerEmail =  $this->_customerSession->getCustomer()->getEmail();
            $customerPhone = $this->_customerSession->getCustomer()->getDefaultShippingAddress()->getTelephone();

            $customerAddress = $this->_customerSession->getCustomer()->getDefaultShippingAddress()->getData();
            $destStreet = $customerAddress['street'];
            $destCity = $customerAddress['city'];
            $destPostCode = $customerAddress['postcode'];
            //$destCountry = "ZA";
        }
        else
        {
            $isLoggedIn = false;


        }

        $store = $this->_storeManager->getStore($request->getStoreId());

        $this->_storeId = $store->getId();
        $this->debugLog("Store ID --", $this->_storeId);

        $this->debugLog("Warehouse ID", $this->getConfigData('warehouseId'));

        $storeWarehouseId = $this->getWarehouseId();


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
                         "street_or_farm" => htmlspecialchars((string) $rawRequest->getDestStreet()),
                         "suburb" => null,
                         "city" => htmlspecialchars((string) $rawRequest->getDestCity()),
                         "postal_code" => htmlspecialchars((string) $rawRequest->getDestPostcode()),
                         "country" => "South Africa",
                         "latitude" => null,
                         "longitude" => null
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
                             "street_or_farm" => htmlspecialchars((string) $rawRequest->getDestStreet()),
                             "suburb" => null,
                             "city" => htmlspecialchars((string) $rawRequest->getDestCity()),
                             "postal_code" => htmlspecialchars((string) $rawRequest->getDestPostcode()),
                             "country" => "South Africa",
                             "latitude" => null,
                             "longitude" => null
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

        if ($this->getConfigData('testMode')) {
            $url = $this->_URI_TEST.$this->_QUOTE_ONE_TO_MANY_TEST;
        } else {
            $url = $this->_URI_LIVE.$this->_QUOTE_ONE_TO_MANY_LIVE;
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

    ///
    /// Posts the JSON Request
    ///
    /// Parameters : $postUrl = The complete post url ()
    ///              $json = the body of the post request
    ///   returns the body of the post response
    ///
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
    public function debugLog ($name = "Debug Msg", $obj){

        if ($this->getConfigData("debug")) {
            //file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/picup_response.txt", "\n" . date("Y-m-d h:i:s") . "<<DBG>>" . $name . " <VAL> " . print_r($obj, 1) . " \n", FILE_APPEND);
            $this->_logger-> debug($name, ["context" => print_r ($obj)]);
        }
    }



    ///
    /// Read the picup_warehouse_shifts table to determine the next available delivery shifts for display in the quotes screen
    /// Weekday ID
    ///
    public function getAvailableShifts(){

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

        $this->debugLog("store id", $this->_storeId);

        //Select Data from table
        $sql = "Select * FROM " . $tableName . " WHERE store_id = " . $this->_storeId;
        $this->debugLog("Shift SQL", $sql);
        $result = $connection->fetchAll($sql); // gives associated array, table fields as key in array.

        $this->debugLog("Record Count", count($result));

        return $result;

    }
}
