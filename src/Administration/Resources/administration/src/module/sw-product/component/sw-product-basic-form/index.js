import { Component } from 'src/core/shopware';
import template from './sw-product-basic-form.html.twig';
import './sw-product-basic-form.less';

Component.register('sw-product-basic-form', {
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
            default() {
                return [];
            }
        }
    }
});
