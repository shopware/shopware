import { Component } from 'src/core/shopware';
import template from './sw-order-delivery.html.twig';
import './sw-order-delivery.less';

Component.register('sw-order-delivery', {
    template,

    props: {
        delivery: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        title: {
            type: String,
            required: false
        },
        isLoading: {
            type: Boolean,
            required: false
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
