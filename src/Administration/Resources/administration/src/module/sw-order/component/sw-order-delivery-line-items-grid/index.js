import { Component } from 'src/core/shopware';
import template from './sw-order-delivery-line-items-grid.html.twig';
import './sw-order-delivery-line-items-grid.less';

Component.register('sw-order-delivery-line-items-grid', {
    template,

    props: {
        deliveryLineItems: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    }
});
