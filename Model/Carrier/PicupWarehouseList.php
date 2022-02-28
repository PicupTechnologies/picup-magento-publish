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

use Magento\Framework\HTTP\ZendClientFactory;

class PicupWarehouseList implements \Magento\Framework\Option\ArrayInterface
{

    protected $_URI_LIVE = 'https://picupafricawebapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST = 'https://picupstaging-webapi.azurewebsites.net/v1/integration/';

    protected $_URI_LIVE_AFRICA = 'https://picupafrica-webapi.azurewebsites.net/v1/integration/';
    protected $_URI_TEST_AFRICA = 'https://picupafrica-webapi.azurewebsites.net/v1/integration/';

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
     * @var  ZendClientFactory
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
     * @var PicUp
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
     * @param ZendClientFactory $httpClientFactory
     * @param PicUp $carrier
     */
    public function __construct(ZendClientFactory                           $httpClientFactory,
                                PicUp                                       $carrier,
                                \Magento\Framework\Message\ManagerInterface $messageManager,
                                \Psr\Log\LoggerInterface                    $logger
    ) {
        $this->_httpClientFactory = $httpClientFactory;
        $this->_carrier = $carrier;
        $this->_messageManager = $messageManager;
        $this->_logger = $logger;
    }

    /**
     * Gets config data
     * @param $field
     * @return mixed
     */
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
        if (empty($this->getConfigData("apiKeyTest")) || empty($this->getConfigData("apiKey"))) return;

        $client = $this->_httpClientFactory->create();

        if ($this->getConfigData('outsideSouthAfrica')) {
            if ($this->getConfigData('testMode')) {
                $detailsUrl = $this->_URI_TEST_AFRICA  . trim($this->getConfigData("apiKeyTest")) . $this->_DETAILS_TEST;
            } else {
                $detailsUrl = $this->_URI_LIVE_AFRICA . trim($this->getConfigData("apiKey")) . $this->_DETAILS_LIVE;
            }
        } else {
            if ($this->getConfigData('testMode')) {
                $detailsUrl = $this->_URI_TEST . trim($this->getConfigData("apiKeyTest")) . $this->_DETAILS_TEST;
            } else {
                $detailsUrl = $this->_URI_LIVE  . trim($this->getConfigData("apiKey")) . $this->_DETAILS_LIVE;
            }
        }



        $client->setUri($detailsUrl);

        $client->setMethod(\Zend_Http_Client::GET);

        if ($this->getConfigData('testMode')) {
            $client->setHeaders('api-key', $this->getConfigData("apiKeyTest"));
        } else {
            $client->setHeaders('api-key', $this->getConfigData("apiKey"));
        }

        try {
            $response = $client->request();
            $this->debugLog("Warehouse List", $response->getBody() );

            $details = json_decode(utf8_decode($response->getBody()));
            $warehouses = $details->warehouses;


        } catch (\Exception $exception) {
            $this->_messageManager->addNoticeMessage("Please setup warehouses on your profile ". $exception->getMessage());
            $this->debugLog("warehouses", $detailsUrl);
            $warehouses = [];
        }

        $warehouseArray = [];

        foreach ($warehouses as $warehouse_id => $warehouse) {
            $this->debugLog("***** id ***** ", $warehouse->warehouse_id);
            $this->debugLog("***** name ***** ", $warehouse->warehouse_name);

            $warehouseArray[$warehouse->warehouse_id] = $warehouse->warehouse_name;
        }

        $this->debugLog("WAREHOUSE ARRAY", $warehouseArray);

        return $warehouseArray;
    }


    /**
     * Get options in "key-value" format
     * @return array
     */
    public function toArray()
    {
        return [0 => __('No'), 1 => __('Yes')];
    }

    /**
     * Debug logger
     * @param string $name
     * @param $obj
     */
    public function debugLog($name = "Debug Msg", $obj)
    {
        if ($this->getConfigData("debug")) {
            $this->_logger->debug($name, ["context" => print_r($obj, 1)]);
        }
    }
}
