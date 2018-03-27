import { Component } from 'src/core/shopware';
import template from './sw-product-settings-form.html.twig';

Component.register('sw-product-settings-form', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
