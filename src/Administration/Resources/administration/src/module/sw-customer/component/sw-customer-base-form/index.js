import { Component } from 'src/core/shopware';
import template from './sw-customer-base-form.html.twig';

Component.register('sw-customer-base-form', {
    template,

    inject: ['swCustomerCreateOnChangeSalesChannel'],

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        },
        salesChannels: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        customerGroups: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        paymentMethods: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    }
});
