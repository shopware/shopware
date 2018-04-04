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
        manufacturers: {
            type: Array,
            required: true,
            default: []
        },
        taxes: {
            type: Array,
            required: true,
            default: []
        },
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    }
});
