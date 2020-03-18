import template from './sw-sales-channel-detail-template.html.twig';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-template', {
    template,

    props: {
        salesChannel: {
            required: true
        }
    }
});
