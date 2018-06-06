import { Component } from 'src/core/shopware';
import template from './sw-order-line-items-grid.html.twig';

Component.register('sw-order-line-items-grid', {
    template,

    props: {
        orderLineItems: {
            type: Array,
            required: true,
            default: []
        }
    },

    computed: {
        lineItems() {
            return this.orderLineItems;
        }
    }
});
