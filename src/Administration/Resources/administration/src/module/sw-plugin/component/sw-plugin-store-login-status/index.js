import { Component } from 'src/core/shopware';
import template from './sw-plugin-store-login-status.html.twig';
import './sw-plugin-store-login-status.scss';

Component.register('sw-plugin-store-login-status', {
    template,

    methods: {
        navigateToAccount() {
            window.open('https://account2.shopware.com', '_blank');
        },

        logout() {
            console.log('logout');
        }
    }
});
