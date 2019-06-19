import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-shopware-account.html.twig';

Component.register('sw-first-run-wizard-shopware-account', {
    template,

    data() {
        return {
            shopwareId: '',
            password: ''
        };
    }
});
