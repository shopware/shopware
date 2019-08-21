import template from './sw-customer-create-base.html.twig';

const { Component } = Shopware;

Component.extend('sw-customer-create-base', 'sw-customer-detail-base', {
    template,

    data() {
        return {
            createMode: true
        };
    }
});
