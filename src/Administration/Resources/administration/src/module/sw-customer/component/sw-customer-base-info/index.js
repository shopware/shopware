import { Component } from 'src/core/shopware';
import template from './sw-customer-base-info.html.twig';

Component.register('sw-customer-base-info', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        }
    },

    methods: {
        onEditCustomer() {
            this.$emit('activateCustomerEditMode');
        }
    }
});
