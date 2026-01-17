define([
    'jquery',
    'ko',
    'mage/url'
], function ($, ko, urlBuilder) {
    'use strict';

    return {
        getCmsBlockContent: function (blockIdentifier) {
            return $.ajax({
                url: urlBuilder.build('rest/V1/cmsblock/get/' + blockIdentifier),
                type: 'GET',
                dataType: 'json'
            }).then(function (response) {
                return response; 
            }).catch(function (xhr) {
                console.error('Error loading CMS block:', xhr);
                return null;
            });
        }
    };
});
