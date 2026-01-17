define(['jquery', 'mage/url', 'mage/loader', 'domReady!'], function ($, urlBuilder) {
    'use strict';

    return function (config, element) {

        var $zipInput = $('#CT_ItemRight_4_txtZipCode');

        function getNewPostcode() {
            var $customZipInput = $('#CT_ItemRight_4_txtZipCode');
            return $customZipInput.length ? $customZipInput.val() : '';
        }

        function showLoader() {
            let $loader = $('[data-role="loader"].loading-mask');
            if (!$loader.length) {
                const loaderImg = require.toUrl('images/loader-1.gif');
                $('body').append(`
                    <div data-role="loader" class="loading-mask">
                        <div class="loader">
                            <img src="${loaderImg}" alt="Loading...">
                        </div>
                    </div>
                `);
            } else {
                $loader.show();
            }
        }

        function hideLoader() {
            $('[data-role="loader"].loading-mask').hide();
        }

        function getQty() {
            var qty = parseInt($('#CT_ItemRight_5_txtSquareFootageBoxes').val())
                || parseInt($('#attribute_qty').val())
                || parseInt($('#qty').val())
                || 1;

            return Math.max(qty, 1);
        }

        $('#btnShip').on('click', function () {
            var qty = getQty();
            let productId = $('input[name="product"]').val();
            let childProductId = $('input[name="selected_configurable_option"]').val() || 0;
            var postcode = getNewPostcode();

            if (!postcode) {
                $zipInput.addClass('required-value');

                var $errorContainer = $('.shipping-estimate-error');

                if ($errorContainer.length) {
                    $errorContainer.text('Please enter zip code.');
                }

                return;
            } else {
                $zipInput.removeClass('required-value');
            }

            var ajaxUrl = urlBuilder.build('shippinginfo/shipping/estimate');

            //Fix loader issue on configurable product
            showLoader();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    zipcode: postcode,
                    qty: qty,
                    child_product_id: childProductId
                },
                success: function (response) {
                    var $errorContainer = $('.shipping-estimate-error');
                    if (response.success) {
                        if ($errorContainer.length) {
                            $errorContainer.text('');
                        }

                        if (response.totals && $('#CT_ItemRight_5_spanSquareFootagePriceTotal').length) {
                            $('#CT_ItemRight_5_spanSquareFootagePriceTotal').text(response.totals.flooring_total);
                        }

                        if (response.shipping && response.shipping.lowest && $('#CT_ItemRight_5_spanShippingCost').length) {
                            $('#CT_ItemRight_5_spanShippingCost').text(response.shipping.lowest.cost);
                        }

                        if (response.totals && $('#CT_ItemRight_5_spanTotal').length) {
                            $('#CT_ItemRight_5_spanTotal').text(response.totals.grand_total);
                        }
                    } else if (response.message && $errorContainer.length) {
                        $errorContainer.text(response.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Shipping estimate AJAX error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });

                    var $errorContainer = $('.shipping-estimate-error');
                    if ($errorContainer.length) {
                        $errorContainer.text('Something went wrong. Please try again.');
                    }
                },
                complete: function () {
                    hideLoader();
                }
            });
        });

        function clearShippingError() {
            var $errorContainer = $('.shipping-estimate-error');
            if ($errorContainer.length) {
                $errorContainer.text('');
            }
        }

        $('#CT_ItemRight_4_txtZipCode').on('input', function () {
            $zipInput.removeClass('required-value');
            clearShippingError();
        });

        $('#CT_ItemRight_5_txtSquareFootageBoxes, #CT_ItemRight_5_txtSquareFootage').on('input', clearShippingError);

        function resetShippingIfChanged() {

            if (currentBoxVal !== prevBoxVal || currentSqftVal !== prevSqftVal) {
                $('#CT_ItemRight_5_spanShippingCost').text('$0.00');
                prevBoxVal = currentBoxVal;
                prevSqftVal = currentSqftVal;
            }
        }

        $('#wastePercent, #CT_ItemRight_5_txtSquareFootageBoxes, #CT_ItemRight_5_txtSquareFootage').on('change', function () {
            $('#CT_ItemRight_5_spanShippingCost').text('$0.00');
        });

    };
});
