import template from './sw-first-run-wizard-shopware-domain.html.twig';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-shopware-domain', {
    template,

    inject: ['firstRunWizardService', 'addNextCallback'],

    data() {
        return {
            licenceDomains: [],
            selectedShopDomain: '',
            createShopDomain: false,
            newShopDomain: '',
            testEnvironment: false,
            domainError: null
        };
    },

    computed: {
        domainToVerify() {
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
            const language = Shopware.State.get('adminLocale').currentLocale;

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

            this.addNextCallback(this.verifyDomain);
        },

        verifyDomain() {
            const { testEnvironment } = this;
            const domain = this.domainToVerify;

            this.domainError = null;

            return this.firstRunWizardService.verifyLicenseDomain({
                domain,
                testEnvironment
            }).then(() => {
                return false;
            }).catch((error) => {
                const msg = error.response.data.errors.pop();

                this.domainError = msg;

                return true;
            });
        }
    }
});
