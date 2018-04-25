import { Component } from 'src/core/shopware';
import template from './sw-customer-base-form.html.twig';

Component.register('sw-customer-base-form', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
