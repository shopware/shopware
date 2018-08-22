import { Component } from 'src/core/shopware';
import template from './sw-customer-create-base.html.twig';

Component.extend('sw-customer-create-base', 'sw-customer-detail-base', {
    template,

    computed: {
        defaultAddress() {
            return this.customer.getAssociation('addresses').getById(this.customer.defaultBillingAddressId);
        }
    }
});
