/**
 * @package system-settings
 */
import template from './sw-settings-search.html.twig';

const { Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [Mixin.getByName('notification')],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSaveSearchSettings',
        },
        ESCAPE: 'onCancel',
    },

    data: () => {
        return {
            productSearchConfigs: {
                andLogic: true,
                minSearchLength: 2,
            },
            isLoading: false,
            currentSalesChannelId: null,
            searchTerms: '',
            searchResults: null,
            defaultConfig: null,
            isSaveSuccessful: false,
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
            leaveConfirmation: false,
            isEditing: false,
        };
    },

    computed: {
        productSearchRepository() {
            return this.repositoryFactory.create('product_search_config');
        },

        productSearchFieldRepository() {
            return this.repositoryFactory.create('product_search_config_field');
        },

        productSearchConfigsCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('configFields');
            criteria.addFilter(Criteria.equals('languageId', Shopware.Context.api.languageId));
            return criteria;
        },

        productDefaultConfigsCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('configFields');
            criteria.addFilter(Criteria.equals('languageId', Shopware.Context.api.systemLanguageId));
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
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },
    },

    created() {
        this.createdComponent();
    },

    beforeRouteUpdate(to, from, next) {
        this.unsavedDataLeaveHandler(to, from, next);
    },

    beforeRouteLeave(to, from, next) {
        this.unsavedDataLeaveHandler(to, from, next);
    },

    methods: {
        createdComponent() {
            this.getDefaultSearchConfig();
            this.getProductSearchConfigs();
        },

        getProductSearchConfigs() {
            this.isLoading = true;
            this.productSearchRepository.search(this.productSearchConfigsCriteria)
                .then((items) => {
                    if (!items.total) {
                        this.onSaveDefaultSearchConfig();
                    } else {
                        this.productSearchConfigs = items.first();
                    }
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getDefaultSearchConfig() {
            this.productSearchRepository.search(this.productDefaultConfigsCriteria)
                .then((items) => {
                    this.defaultConfig = items.first();
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                });
        },

        createDefaultSearchConfig() {
            const defaultConfig = this.productSearchRepository.create();
            defaultConfig.andLogic = this.defaultConfig.andLogic;
            defaultConfig.minSearchLength = this.defaultConfig.minSearchLength;
            defaultConfig.excludedTerms = [];
            defaultConfig.languageId = Shopware.Context.api.languageId;
            return defaultConfig;
        },

        createConfigFields() {
            if (!this.defaultConfig || !this.defaultConfig.configFields.length) {
                return null;
            }

            const configFieldCollection = new EntityCollection(
                this.productSearchFieldRepository.route,
                this.productSearchFieldRepository.entityName,
                Shopware.Context.api,
            );
            this.defaultConfig.configFields.forEach(item => {
                const newConfigField = this.productSearchFieldRepository.create();
                newConfigField.field = item.field;
                newConfigField.ranking = item.ranking;
                newConfigField.searchable = item.searchable;
                newConfigField.tokenize = item.tokenize;
                newConfigField.customFieldId = null;
                newConfigField.searchConfigId = this.productSearchConfigs.id;
                configFieldCollection.add(newConfigField);
            });
            return configFieldCollection;
        },

        onSaveDefaultSearchConfig() {
            this.productSearchConfigs = this.createDefaultSearchConfig();
            this.productSearchConfigs.configFields = this.createConfigFields();
            this.productSearchRepository.save(this.productSearchConfigs)
                .then(() => {
                    this.getProductSearchConfigs();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError'),
                    });
                });
        },

        onChangeLanguage() {
            this.getDefaultSearchConfig();
            this.getProductSearchConfigs();
        },

        onTabChange() {
            this.getProductSearchConfigs();
        },

        onSaveSearchSettings() {
            this.isLoading = true;
            this.productSearchRepository.save(this.productSearchConfigs)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-search.notification.saveSuccess'),
                    });
                    this.getProductSearchConfigs();
                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                    this.isEditing = false;
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        fetchSalesChannels() {
            this.salesChannelRepository.search(new Criteria(1, 25)).then((response) => {
                this.salesChannels = response;
            });
        },

        unsavedDataLeaveHandler(to, from, next) {
            if (this.leaveConfirmation) {
                this.leaveConfirmation = false;
                next();

                return;
            }

            if (this.isEditing) {
                this.isDisplayingLeavePageWarning = true;
                this.nextRoute = to;
                next(false);
            } else {
                next();
            }
        },

        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
        },

        onLiveSearchResultsChanged({ searchTerms, searchResults }) {
            this.searchTerms = searchTerms;
            this.searchResults = searchResults;
        },

        onEditChanged(isEditing) {
            this.isEditing = isEditing;
        },

        onConfirmLeave() {
            this.leaveConfirmation = true;
            this.isDisplayingLeavePageWarning = false;
            this.isEditing = false;

            this.$nextTick(() => {
                this.$router.push({
                    name: this.nextRoute.name,
                    params: this.nextRoute.params,
                });
            });
        },

        onCloseLeaveModal() {
            this.isDisplayingLeavePageWarning = false;
        },

        onCancelLeaveModal() {
            this.isDisplayingLeavePageWarning = false;
        },
    },
};
