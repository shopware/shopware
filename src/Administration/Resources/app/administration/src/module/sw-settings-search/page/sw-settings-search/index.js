import template from './sw-settings-search.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-search', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    data: () => {
        return {
            productSearchConfigs: {
                andLogic: true,
                minSearchLength: 2
            },
            isLoading: false
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        productSearchRepository() {
            return this.repositoryFactory.create('product_search_config');
        },

        productSearchConfigsCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('configFields');
            criteria.addFilter(Criteria.equals('languageId', Shopware.Context.api.languageId));

            return criteria;
        }
    },

    methods: {
        createdComponent() {
            this.getProductSearchConfigs();
        },

        getProductSearchConfigs() {
            this.isLoading = true;
            this.productSearchRepository.search(this.productSearchConfigsCriteria, Shopware.Context.api)
                .then((items) => {
                    this.productSearchConfigs = items.first();
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onChangeLanguage() {
            this.getProductSearchConfigs();
        },

        onSaveSearchSettings() {
            this.isLoading = true;
            this.productSearchRepository.save(this.productSearchConfigs, Shopware.Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-search.notification.saveSuccess')
                    });
                    this.getProductSearchConfigs();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError')
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        }
    }
});
