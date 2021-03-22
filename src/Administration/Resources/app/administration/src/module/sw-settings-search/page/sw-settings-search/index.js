import template from './sw-settings-search.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-search', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    mixins: [Mixin.getByName('notification')],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSaveSearchSettings'
        },
        ESCAPE: 'onCancel'
    },

    data: () => {
        return {
            productSearchConfigs: {
                andLogic: true,
                minSearchLength: 2
            },
            isLoading: false,
            currentSalesChannelId: null,
            searchTerms: '',
            searchResults: null
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
        },

        allowSave() {
            return this.acl.can('product_search_config.editor') || this.acl.can('product_search_config.creator');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
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

        onTabChange() {
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
        },

        fetchSalesChannels() {
            this.salesChannelRepository.search(new Criteria(), Shopware.Context.api).then((response) => {
                this.salesChannels = response;
            });
        },

        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
        },

        onLiveSearchResultsChanged({ searchTerms, searchResults }) {
            this.searchTerms = searchTerms;
            this.searchResults = searchResults;
        }
    }
});
