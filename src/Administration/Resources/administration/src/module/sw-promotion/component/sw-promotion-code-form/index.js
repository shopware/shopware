import { Component, Mixin } from 'src/core/shopware';
import template from './sw-promotion-code-form.html.twig';

Component.register('sw-promotion-code-form', {
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
