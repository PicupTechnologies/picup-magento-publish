<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Picup\Shipping\Model\Attribute\Backend;

class AvailableDelivery extends \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
{
    /**
     * Validate
     * @param \Magento\Catalog\Model\Product $object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return bool
     */
    public function validate($object): bool
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());
        
//        if ( ($object->getAttributeSetId() == 10) && ($value == 'wool')) {
//            throw new \Magento\Framework\Exception\LocalizedException(
//                __('Bottom can not be wool.')
//            );
//        }

        return true;
    }
}
