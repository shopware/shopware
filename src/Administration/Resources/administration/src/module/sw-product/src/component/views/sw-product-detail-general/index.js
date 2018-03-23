import { Component } from 'src/core/shopware';
import template from './sw-product-detail-general.html.twig';

Component.register('sw-product-detail-general', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        manufacturers: {
            type: Array,
            required: true,
            default: []
        },
        taxRates: {
            type: Array,
            required: true,
            default: []
        },
        serviceProvider: {
            type: Object,
            required: true
        }
    }
});
