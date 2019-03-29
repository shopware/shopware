import { Component, Mixin } from 'src/core/shopware';
import template from './sw-promotion-basic-form.html.twig';

Component.register('sw-promotion-basic-form', {
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
