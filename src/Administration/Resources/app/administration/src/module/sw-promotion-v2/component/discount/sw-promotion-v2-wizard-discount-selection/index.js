import template from './sw-promotion-v2-wizard-discount-selection.html.twig';

const { Component } = Shopware;

Component.extend('sw-promotion-v2-wizard-discount-selection', 'sw-wizard-page', {
    template,

    data() {
        return {
            value: 'basic',
            modalTitle: this.$tc('sw-promotion-v2.detail.discount-selection.modalTitle'),
        };
    },

    methods: {
        getSelectionOptions() {
            return [{
                value: 'basic',
                name: this.$tc('sw-promotion-v2.detail.discount-selection.basic.name'),
                description: this.$tc('sw-promotion-v2.detail.discount-selection.basic.description'),
            }, {
                value: 'buy-x-get-y',
                name: this.$tc('sw-promotion-v2.detail.discount-selection.buy-x-get-y.name'),
                description: this.$tc('sw-promotion-v2.detail.discount-selection.buy-x-get-y.description'),
            }, {
                value: 'shipping-discount',
                name: this.$tc('sw-promotion-v2.detail.discount-selection.shipping-discount.name'),
                description: this.$tc('sw-promotion-v2.detail.discount-selection.shipping-discount.description'),
            }];
        },

        onChangeSelection(value) {
            this.$emit('change-selection', value);
        },
    },
});
