import { Component } from 'src/core/shopware';
import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.less';

Component.register('sw-order-line-items-grid', {
    template,

    props: {
        orderLineItems: {
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
