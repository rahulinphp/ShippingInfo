require([
    'jquery',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'mage/url'
], function ($, customer, quote, selectShippingMethodAction, urlBuilder) {
    'use strict';

    function getNewPostcode() {
        var $customZipInput = $('#CT_ItemRight_4_txtZipCode');
        return $customZipInput.length ? $customZipInput.val() : '';
    }

    function getShippingAddressPayload(postcode) {
        var countryId = quote.shippingAddress() ? quote.shippingAddress().countryId : 'US';
        var regionId = quote.shippingAddress() ? quote.shippingAddress().regionId : null;

        return {
            country_id: countryId,
            postcode: postcode,
            region: '',
            region_id: regionId
        };
    }

    function getEstimateUrl(isLoggedIn, quoteId) {
        return isLoggedIn
            ? urlBuilder.build('rest/V1/carts/mine/estimate-shipping-methods')
            : urlBuilder.build('rest/V1/guest-carts/' + quoteId + '/estimate-shipping-methods');
    }

    function showLoader($element) {
        $element.addClass('_block-content-loading');
        if ($element.find('.loader').length === 0) {
            $element.prepend('<div class="loader"></div>');
        }
    }

    function hideLoader($element) {
        $element.removeClass('_block-content-loading');
        $element.find('.loader').remove();
    }

    function applyCheapestShippingMethod(methods) {
        if (!methods.length) {
            console.warn('No shipping methods available.');
            return;
        }

        var selectedMethod = methods.reduce(function (a, b) {
            return parseFloat(a.amount) < parseFloat(b.amount) ? a : b;
        });

        selectShippingMethodAction(selectedMethod);
    }

    function estimateShipping(postcode) {
        var isLoggedIn = customer.isLoggedIn();
        var quoteId = quote.getQuoteId();
        var estimateUrl = getEstimateUrl(isLoggedIn, quoteId);
        var addressPayload = { address: getShippingAddressPayload(postcode) };

        var $totalsWrapper = $('#cart-totals .table-wrapper');
        showLoader($totalsWrapper);

        $.ajax({
            url: estimateUrl,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(addressPayload),
            success: function (methods) {
                hideLoader($totalsWrapper);
                applyCheapestShippingMethod(methods);
            },
            error: function (xhr) {
                hideLoader($totalsWrapper);
                console.error('Error estimating shipping methods:', xhr.responseText);
            }
        });
    }

    $(document).on('click', '#btnShip', function () {
        var postcode = getNewPostcode();
        var $zipInput = $('#CT_ItemRight_4_txtZipCode');

        if (!postcode) {
            $zipInput.addClass('required-value');
            return;
        } else {
            $zipInput.removeClass('required-value');
        }

        if (quote.shippingAddress()) {
            quote.shippingAddress().postcode = postcode;
        }

        estimateShipping(postcode);
    });

    $(document).on('input', '#CT_ItemRight_4_txtZipCode', function () {
        if ($(this).val().trim() !== '') {
            $(this).removeClass('required-value');
        }
    });
});
