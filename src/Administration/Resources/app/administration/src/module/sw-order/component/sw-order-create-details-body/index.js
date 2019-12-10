import template from './sw-order-create-details-body.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-details-body', {
    template,

    props: {
        customer: {
            type: Object,
            required: true
        },

        isCustomerActive: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    methods: {
        onEditBillingAddress() {
            this.$emit('on-edit-billing-address');
        },

        onEditShippingAddress() {
            this.$emit('on-edit-shipping-address');
        }
    }
});
