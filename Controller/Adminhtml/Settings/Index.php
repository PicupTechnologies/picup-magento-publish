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

namespace Picup\Shipping\Controller\Adminhtml\Settings;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;


    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
     ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;

    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Picup_Shipping::main');
        $resultPage->getConfig()->getTitle()->prepend(__('Picup'));
        return $resultPage;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException( RequestInterface $request ): ?       InvalidRequestException {
        return null;
    }

    /** * @inheritDoc */
    public function validateForCsrf(RequestInterface $request): ?bool {
        return true;
    }

}
