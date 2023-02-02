import template from './sw-price-rule-modal.html.twig';

const { Component } = Shopware;

Component.extend('sw-price-rule-modal', 'sw-rule-modal', {
    template,

    computed: {
        modalTitle() {
            return this.$tc('sw-settings-shipping.shippingPriceModal.modalTitle');
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');
            this.rule.moduleTypes = { types: ['shipping'] };
        },

    },
});
