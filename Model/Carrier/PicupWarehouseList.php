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

/**
 * @api
 * @since 100.0.2
 */
class PicupWarehouseList implements \Magento\Framework\Option\ArrayInterface
{

    protected $_URI_LIVE = 'https://otdcpt-knupprd.onthedot.co.za/picup-api/v1/integration/';
    protected $_URI_TEST = 'https://otdcpt-knupqa.onthedot.co.za/picup-api/v1/integration/';


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

    public function __construct(\Magento\Framework\HTTP\ZendClientFactory $httpClientFactory)
    {
        $this->_httpClientFactory = $httpClientFactory;
    }


    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $client = $this->_httpClientFactory->create();
        $client->setUri('https://otdcpt-knupqa.onthedot.co.za/picup-api/v1/integration/business-a86db193-af45-4022-8070-80b9abb0f115/details');
        $client->setMethod(\Zend_Http_Client::GET);
        $response = $client->request();

        $details = json_decode($response->getBody());

        $warehouses = $details->warehouses;

        $warehouseArray = [];

        foreach($warehouses as $warehouse_id => $warehouse)
        {
            $this->debugLog("***** id ***** ", $warehouse->warehouse_id);
            $this->debugLog("***** name ***** ", $warehouse->warehouse_name);

            /*
            $warehouseArray [] =
                (object)[
                    "value" => $warehouse->warehouse_id,
                    "label" => $warehouse->warehouse_name
                ];
            */

            $warehouseArray[] = $warehouse->warehouse_name;

        }

        $this->debugLog("WAAREHOUSE ARRAY", $warehouseArray);

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
        $this->debugLog("WAAREHOUSE ARRAY - Create", $warehouseArray);
    }

    public function debugLog ($name = "Debug Msg", $obj){

        //if ($this->getConfigData("debug")) {
            file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/picup_response.txt", "\n" . date("Y-m-d h:i:s") . "<<DBG>>" . $name . " <VAL> " . print_r($obj, 1), FILE_APPEND);
        //}
    }
}
