import { Component, Mixin } from 'src/core/shopware';
import template from './sw-promotion-rebate-form.html.twig';

Component.register('sw-promotion-rebate-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
