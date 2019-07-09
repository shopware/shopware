import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-shopware-domain.html.twig';

Component.register('sw-first-run-wizard-shopware-domain', {
    template,

    inject: ['firstRunWizardService'],

    data() {
        return {
            licenceDomains: [],
            selectedShopDomain: '',
            createShopDomain: false,
            newShopDomain: '',
            testEnvironment: false
        };
    },

    computed: {
        verifyDomain() {
            return this.createShopDomain
                ? this.newShopDomain
                : this.selectedShopDomain;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const language = 'de_DE';

            this.firstRunWizardService.getLicenseDomains({
                language
            }).then((response) => {
                const { items } = response;

                if (!items || items.length < 1) {
                    return;
                }

                this.licenceDomains = items;
                this.selectedShopDomain = items[0].domain;
            });
        },

        onSelectDomain() {
            const domain = this.verifyDomain;

            if (!domain) {
                return;
            }

            this.firstRunWizardService.verifyLicenseDomain({
                domain
            }).then((response) => {
                console.log(response);
            }).catch((error) => {
                console.warn(error);
            });
        }
    }
});
