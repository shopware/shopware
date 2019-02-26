import { Component } from 'src/core/shopware';
import template from './sw-customer-create-base.html.twig';

Component.extend('sw-customer-create-base', 'sw-customer-detail-base', {
    template,

    data() {
        return {
            createMode: true,
            defaultAddress: null
        };
    },

    watch: {
        customer() {
            this.defaultAddress = this.customer.getAssociation('addresses').getById(this.customer.defaultBillingAddressId);
        }
    },

    created() {
        this.defaultAddress = this.customer.getAssociation('addresses').getById(this.customer.defaultBillingAddressId);
    }
});
