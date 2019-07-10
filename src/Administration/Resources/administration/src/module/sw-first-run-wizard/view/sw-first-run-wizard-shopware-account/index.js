import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-shopware-account.html.twig';
import './sw-first-run-wizard-shopware-account.scss';

Component.register('sw-first-run-wizard-shopware-account', {
    template,

    inject: ['firstRunWizardService', 'addNextCallback'],

    data() {
        return {
            shopwareId: '',
            password: '',
            accountError: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.addNextCallback(this.testCredentials);
        },

        testCredentials() {
            const { shopwareId, password } = this;
            const language = this.$store.state.adminLocale.currentLocale;

            return this.firstRunWizardService.checkShopwareId({
                language,
                shopwareId,
                password
            }).then(() => {
                this.accountError = false;

                return false;
            }).catch(() => {
                this.accountError = true;

                return true;
            });
        }
    }
});
