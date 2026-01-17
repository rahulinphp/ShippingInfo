/* jshint browser:true jquery:true */

config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Hardwoods_ShippingInfo/js/checkout/shipping-mixin': true
            }
        }
    },
    map: {
        '*': {
            shippingCalculate: 'Hardwoods_ShippingInfo/js/shipping-calculate'
        }
    }
};
