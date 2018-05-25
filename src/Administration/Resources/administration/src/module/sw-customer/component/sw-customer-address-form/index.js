import { Component } from 'src/core/shopware';
import template from './sw-customer-address-form.html.twig';

Component.register('sw-customer-address-form', {
    template,

    props: {
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
