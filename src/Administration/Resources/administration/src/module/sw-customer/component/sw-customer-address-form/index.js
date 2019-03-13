import { Component } from 'src/core/shopware';
import template from './sw-customer-address-form.html.twig';
import './sw-customer-address-form.scss';

Component.register('sw-customer-address-form', {
    template,

    props: {
        customer: {
            type: Object,
            required: true
        },

        address: {
            type: Object,
            required: true,
            default: {}
        },

        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    }
});
