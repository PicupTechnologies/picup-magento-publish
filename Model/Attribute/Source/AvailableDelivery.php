<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Picup\Shipping\Model\Attribute\Source;

class AvailableDelivery extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Get all options
     * @return array
     */
    public function getAllOptions(): array
    {
        if (!$this->_options) {
            $this->_options = [
                ['label' => __('Yes'), 'value' => 'Yes'],
                ['label' => __('No'), 'value' => 'No'],
            ];
        }
        return $this->_options;
    }
}
