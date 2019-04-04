import { Component } from 'src/core/shopware';
import template from './sw-product-detail-base.html.twig';

Component.register('sw-product-detail-base', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        manufacturerStore: {
            type: Object,
            required: true
        },
        taxes: {
            type: Array,
            required: true,
            default: []
        },
        currencies: {
            type: Array,
            required: true,
            default: []
        },
        attributeSets: {
            type: Array,
            required: true,
            default: []
        }
    },

    methods: {
        priceIsCalculating(value) {
            this.$emit('calculating', value);
        }
    }
});
