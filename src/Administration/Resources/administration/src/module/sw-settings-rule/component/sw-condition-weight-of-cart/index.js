import { Component } from 'src/core/shopware';
import template from './sw-condition-weight-of-cart.html.twig';

Component.extend('sw-condition-weight-of-cart', 'sw-condition-base', {
    template,

    computed: {
        fieldNames() {
            return ['operator', 'weight'];
        },
        defaultValues() {
            return {
                weight: 0.0
            };
        }
    }
});
