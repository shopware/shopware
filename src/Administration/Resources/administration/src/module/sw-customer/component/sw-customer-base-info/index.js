import { Component } from 'src/core/shopware';
import template from './sw-customer-base-info.html.twig';
import './sw-customer-base-info.scss';

Component.register('sw-customer-base-info', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
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
        },
        languages: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        customerEditMode: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    methods: {
        onEditCustomer() {
            this.$emit('activateCustomerEditMode');
        }
    }
});
