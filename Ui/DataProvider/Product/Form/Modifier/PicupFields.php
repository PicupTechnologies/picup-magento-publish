<?php

namespace Picup\Shipping\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Data\Form\Element\Checkbox;
use Magento\TestFramework\Catalog\Model\Product\Option\DataProvider\Type\RadioButtons;
use Magento\Ui\Component\Form\Element\CheckboxSet;
use Magento\Ui\Component\Form\Element\RadioSet;
use Magento\Ui\Component\Form\Fieldset;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Element\DataType\Text;

class PicupFields extends AbstractModifier
{
    private $locator;
    protected $_resourceConnection;
    protected $_objectManager;

    public function __construct(
        LocatorInterface $locator
    )
    {
        $this->locator = $locator;
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_resourceConnection = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');

    }

    /**
     * Gets all the shifts for a store
     * @param $storeId
     * @return mixed
     */
    public function getShifts($storeId) {
        if ($storeId == 0) {
            //All stores
            $where = "";
        } else {
            $where = "WHERE store_id = " . $storeId;
        }
        $tableName = $this->_resourceConnection->getTableName('picup_warehouse_shifts');
        $sql = "Select * FROM " . $tableName . " {$where}";
        $connection = $this->_resourceConnection->getConnection();
        return $connection->fetchAll($sql);
    }


    /**
     * Gets the product data by product sku and store
     * @param $productSku
     * @param $storeId
     * @return array|mixed
     */
    public function getProductData($productSku, $storeId) {
        $where = "WHERE store_id = " . $storeId." and product_sku = '{$productSku}'";
        $tableName = $this->_resourceConnection->getTableName('picup_products');
        $sql = "Select * FROM " . $tableName . " {$where}";
        $connection = $this->_resourceConnection->getConnection();
        $productData = $connection->fetchAll($sql);



        if (is_array($productData) && !empty($productData)) {
            $productData = $productData[0];
            $productData["shift_data"] = unserialize($productData["shift_data"]);
        } else {
            $productData = null;
        }
        return $productData;
    }


    /**
     * Modify data method returns the data that has been stored
     * @param array $data
     * @return array
     */
    public function modifyData(array $data)
    {
        foreach ($data as $key => $value) {
            if (!isset($value["product"])) continue;
            if (empty($value["product"])) {
                $data[$key]["product"]["picup"]["picup_enabled"] = '1';
                $data[$key]["product"]["picup"]["picup_width"] = '1';
                $data[$key]["product"]["picup"]["picup_height"] = '1';
                $data[$key]["product"]["picup"]["picup_length"] = '1';
            } else {
                $productSku = $value["product"]["sku"];
                $productData = $this->getProductData($productSku, $this->locator->getStore()->getId());
                $data[$key]["product"]["picup"]["picup_enabled"] = $productData["picup_enabled"];
                $data[$key]["product"]["picup"]["picup_width"] = $productData["picup_width"];
                $data[$key]["product"]["picup"]["picup_height"] = $productData["picup_height"];
                $data[$key]["product"]["picup"]["picup_length"] = $productData["picup_length"];
                $data[$key]["product"]["picup"]["picup_shifts"] = $productData["shift_data"];
            }
        }

        return $data;
    }

    /**
     * Return the custom fields for the product form
     * @param array $meta
     * @return array
     */
    public function modifyMeta(array $meta)
    {
        $meta = array_replace_recursive(
            $meta,
            [
                'picup_fields' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Picup Settings'),
                                'componentType' => Fieldset::NAME,
                                'dataScope' => 'data.product.picup',
                                'collapsible' => true,
                                'sortOrder' => 5,
                            ],
                        ],
                    ],
                    'children' => [
                        'picup_enabled' => $this->getPicupEnabled(),
                        'picup_width'  => $this->getPicupWidth(),
                        'picup_height' => $this->getPicupHeight(),
                        'picup_length' => $this->getPicupLength(),
                        'picup_shifts' => $this->getPicupShifts()
                    ],
                ]
            ]
        );
        return $meta;
    }

    /**
     * Picup Enabled Input
     * @return \array[][][]
     */
    public function getPicupEnabled()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Picup Enabled'),
                        'componentType' => Field::NAME,
                        'formElement' => RadioSet::NAME,
                        'dataType' => Text::NAME,
                        'sortOrder' => 1,
                        'value' => '1',
                        'options' => [
                            ['value' => '1', 'label' => __('Yes')],
                            ['value' => '0', 'label' => __('No')]
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Width Component
     * @return \array[][][]
     */
    public function getPicupWidth()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Product Width (cms)'),
                        'componentType' => Field::NAME,
                        'formElement' => \Magento\Ui\Component\Form\Element\Input::NAME,
                        'dataType' => Text::NAME,
                        'sortOrder' => 10,
                    ],
                ],
            ],
        ];
    }

    /**
     * Height Component
     * @return \array[][][]
     */
    public function getPicupHeight()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Product Height (cms)'),
                        'componentType' => Field::NAME,
                        'formElement' => \Magento\Ui\Component\Form\Element\Input::NAME,
                        'dataType' => Text::NAME,
                        'sortOrder' => 20,
                    ],
                ],
            ],
        ];
    }

    /**
     * Length Component
     * @return \array[][][]
     */
    public function getPicupLength()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Product Length (cms)'),
                        'componentType' => Field::NAME,
                        'formElement' => \Magento\Ui\Component\Form\Element\Input::NAME,
                        'dataType' => Text::NAME,
                        'sortOrder' => 30,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get all the picup shifts for the selected store on the product screen
     * @return \array[][][]
     */
    public function getPicupShifts() {
        $shifts = $this->getShifts($this->locator->getStore()->getId());

        $shiftChoices = [];
        $countShifts = 0;
        foreach ($shifts as $id => $shift) {
            $shiftChoices[] = ['value' => $shift["id"], 'label' => __($shift["description"])];
            $countShifts++;
        }

        return [
            'arguments' => [
                'data' =>[
                    'config' => [
                        'label' => __('Picup Shifts'),
                        'componentType' => Field::NAME,
                        'formElement' => CheckboxSet::NAME,
                        'dataType' => Text::NAME,
                        'sortOrder' => 40,
                        'options' => $shiftChoices
                    ],
                ]
            ],
        ];
    }
}
