/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'mdt_cyberpay',
                component: 'MDT_Cyberpay/js/view/payment/method-renderer/mdt_cyberpay'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
