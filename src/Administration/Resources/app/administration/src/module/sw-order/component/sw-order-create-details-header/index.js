import template from './sw-order-create-details-header.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-details-header', {
    template,

    props: {
        customer: {
            type: Object,
            default: {}
        },

        orderDate: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            customerId: ''
        };
    },

    methods: {
        onSelectExistingCustomer(customerId) {
            this.$emit('on-select-existing-customer', customerId);
        },

        onAddNewCustomer() {
            this.$emit('on-add-new-customer');
        }
    }
});
