import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-shopware-account.html.twig';
import './sw-first-run-wizard-shopware-account.scss';

Component.register('sw-first-run-wizard-shopware-account', {
    template,

    inject: ['firstRunWizardService'],

    data() {
        return {
            shopwareId: '',
            password: '',
            accountError: false
        };
    },

    methods: {
        testCredentials() {
            const { shopwareId, password } = this;
            // ToDo: (mve) use adminLang
            const language = 'de_DE';

            this.firstRunWizardService.checkShopwareId({
                language,
                shopwareId,
                password
            }).then(() => {
                this.accountError = false;
            }).catch(() => {
                this.accountError = true;
            });
        }
    }
});
