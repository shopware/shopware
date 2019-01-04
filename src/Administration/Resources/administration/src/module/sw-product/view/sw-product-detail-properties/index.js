import { Component } from 'src/core/shopware';
import template from './sw-product-detail-properties.html.twig';

Component.register('sw-product-detail-properties', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        datasheetStore() {
            return this.product.getAssociation('datasheet');
        }
    }
});
