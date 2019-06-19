import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-paypal-credentials.html.twig';

Component.register('sw-first-run-wizard-paypal-credentials', {
    template,

    data() {
        return {
            clientId: '',
            clientSecret: '',
            sandboxEnabled: false
        };
    },

    methods: {
        onGetAPICredentials() {
            alert('onGetAPICredentials was pressed');
        }
    }
});
