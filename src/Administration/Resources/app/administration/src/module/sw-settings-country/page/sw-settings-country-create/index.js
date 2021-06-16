import template from './sw-settings-country-create.html.twig';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-settings-country-create', 'sw-settings-country-detail', {
    template,

    inject: ['feature'],

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.country.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;

            if (this.$route.params.id) {
                this.country = this.countryRepository.create(Shopware.Context.api, this.$route.params.id);
                if (this.feature.isActive('FEATURE_NEXT_14114')) {
                    this.country.customerTax = {
                        amount: 0,
                        currencyId: Shopware.Context.app.systemCurrencyId,
                        enabled: false,
                    };
                    this.country.companyTax = {
                        amount: 0,
                        currencyId: Shopware.Context.app.systemCurrencyId,
                        enabled: false,
                    };
                }
                this.countryId = this.country.id;
                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source,
                );
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.country.detail', params: { id: this.country.id } });
        },
    },
});
