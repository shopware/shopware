import { Component } from 'src/core/shopware';
import template from './sw-order-delivery-metadata.html.twig';
import './sw-order-delivery-metadata.less';

Component.register('sw-order-delivery-metadata', {
    template,

    props: {
        delivery: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        order: {
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
            required: false,
            default: false
        }
    }
});
