<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Picup\Shipping\Model\Carrier;

/**
 * @api
 * @since 100.0.2
 */
class PicupWarehouseList implements \Magento\Framework\Option\ArrayInterface
{

    protected $_URI_LIVE = 'https://picupprod-webapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST = 'https://picupstaging-webapi.azurewebsites.net/v1/integration/';

    protected $_DETAILS_LIVE = '/details';
    protected $_DETAILS_TEST = '/details';


    protected $_code = "picup";

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
     * @var \Picup\Shipping\Model\Carrier\PicUp
     */
    protected $_carrier;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * PicupWarehouseList constructor.
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     * @param \Picup\Shipping\Model\Carrier\PicUp $carrier
     */
    public function __construct(\Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
                                \Picup\Shipping\Model\Carrier\PicUp $carrier,
                                \Magento\Framework\Message\ManagerInterface $messageManager,
                                \Psr\Log\LoggerInterface $logger
    )
    {
        $this->_httpClientFactory = $httpClientFactory;
        $this->_carrier = $carrier;
        $this->_messageManager = $messageManager;
        $this->_logger = $logger;
    }


    public function getConfigData($field)
    {
       return $this->_carrier->getConfigData($field);
    }


    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {


        if (empty($this->getConfigData("apiKeyTest")) || empty($this->getConfigData("apiKey"))  ) return;

        $client = $this->_httpClientFactory->create();

        if ($this->getConfigData('testMode')) {
            $detailsUrl = $this->_URI_TEST.$this->getConfigData("apiKeyTest").$this->_DETAILS_TEST;
        } else {
            $detailsUrl = $this->_URI_LIVE.$this->getConfigData("apiKey").$this->_DETAILS_LIVE;
        }

        $client->setUri($detailsUrl);

        $client->setMethod(\Zend_Http_Client::GET);

        try {

            $response = $client->request();
            $details = json_decode($response->getBody());
            $warehouses = $details->warehouses;

        } catch (\Exception $exception) {
            $this->_messageManager->addNoticeMessage("Please setup warehouses on your profile");
            $warehouses =[];
        }

        $warehouseArray = [];

        foreach($warehouses as $warehouse_id => $warehouse)
        {
            $this->debugLog("***** id ***** ", $warehouse->warehouse_id);
            $this->debugLog("***** name ***** ", $warehouse->warehouse_name);



            $warehouseArray[$warehouse->warehouse_id] = $warehouse->warehouse_name;

        }

        $this->debugLog("WAREHOUSE ARRAY", $warehouseArray);

        //return [['value' => 1, 'label' => __('Province')], ['value' => 0, 'label' => __('Rural')]];
        return $warehouseArray;

    }



    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [0 => __('No'), 1 => __('Yes')];
        $this->debugLog("WAREHOUSE ARRAY - Create", $warehouseArray);
    }

    public function debugLog ($name = "Debug Msg", $obj){
        if ($this->getConfigData("debug")) {
            //file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/picup_response.txt", "\n" . date("Y-m-d h:i:s") . "<<DBG>>" . $name . " <VAL> " . print_r($obj, 1), FILE_APPEND);
            $this->_logger-> debug($name, ["context" => print_r ($obj)]);
        }
    }
}
