import template from './sw-promotion-v2-wizard-shipping-discount-trigger.html.twig';

const { Component } = Shopware;

Component.extend('sw-promotion-v2-wizard-shipping-discount-trigger', 'sw-wizard-page', {
    template,

    data() {
        return {
            modalTitle: this.$tc('sw-promotion-v2.detail.shipping-discount-trigger.modalTitle')
        };
    }
});
