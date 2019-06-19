import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-shopware-domain.html.twig';

Component.register('sw-first-run-wizard-shopware-domain', {
    template,

    data() {
        return {
            createShopDomain: false,
            newShopDomain: '',
            selectedShopDomain: '',
            testEnvironment: false
        };
    }
});
