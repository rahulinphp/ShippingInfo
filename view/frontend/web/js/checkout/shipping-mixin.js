define([
    'jquery',
    'Hardwoods_ShippingInfo/js/model/cms-block',
    'ko'
], function ($, cmsBlock, ko) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({

            blockContent: ko.observable(''),

            initialize: function () {
                this._super();
                this.loadCmsBlock();
                return this;
            },

            /**
             * Load CMS block content and update observable
             */
            loadCmsBlock: function () {
                var self = this;
                cmsBlock.getCmsBlockContent('shipping_description_checkout_page')
                    .then(function (content) {
                        self.blockContent(content || '');
                    })
                    .catch(function (error) {
                        console.error('Error loading CMS block:', error);
                        self.blockContent('');
                    });
            },

            /**
             * Get CMS block content observable
             * @returns {KnockoutObservable}
             */
            getCustomShippingBlock: function () {
                return this.blockContent;
            }
        });
    };
});
