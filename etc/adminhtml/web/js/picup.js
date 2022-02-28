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
define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal, $t) {
    'use strict';

    return function (config, element) {
        config.buttons = [
            {
                text: $t('Print'),
                'class': 'action action-primary',

                /**
                 * Click handler
                 */
                click: function () {
                    window.location.href = this.options.url;
                }
            }, {
                text: $t('Cancel'),
                'class': 'action action-secondary',

                /**
                 * Click handler
                 */
                click: function () {
                    this.closeModal();
                }
            }
        ];
        modal(config, element);
    };
});
