import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-basic-form.html.twig';

Component.register('sw-product-basic-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },

        manufacturerStore: {
            type: Object,
            required: true
        }
    }
});
