import template from './sw-sales-channel-detail-products.html.twig';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-products', {
    template,

    props: {
        salesChannel: {
            required: true
        }
    }
});
