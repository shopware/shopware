import { Component } from 'src/core/shopware';
import template from './sw-plugin-store-login-status.html.twig';
import './sw-plugin-store-login-status.scss';

// TODO implementation with NEXT-1901
Component.register('sw-plugin-store-login-status', {
    template,

    methods: {
        logout() {
        }
    }
});
