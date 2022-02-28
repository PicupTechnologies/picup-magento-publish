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

namespace Picup\Shipping\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class RefreshWareHouseList extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Picup_Shipping::system/config/refreshwarehouseList.phtml';

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ){
        parent::__construct($context, $data);
    }

}
