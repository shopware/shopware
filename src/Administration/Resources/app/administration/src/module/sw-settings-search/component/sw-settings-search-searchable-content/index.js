/**
 * @package system-settings
 */
import template from './sw-settings-search-searchable-content.html.twig';
import './sw-settings-search-searchable-content.scss';

const { Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        searchConfigId: {
            type: String,
            required: true,
        },

        productSearchConfigs: {
            type: Object,
            required: false,
            default() {
                return null;
            },
        },
    },

    data() {
        return {
            showExampleModal: false,
            defaultTab: 'general',
            tabNames: {
                generalTab: 'general',
                customTab: 'customfields',
            },
            isLoading: false,
            isEnabledReset: true,
            searchConfigFields: [],
            fieldConfigs: [
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.name'),
                    value: 'name',
                    defaultConfigs: {
                        searchable: true,
                        ranking: 700,
                        tokenize: true,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.description'),
                    value: 'description',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.productNumber'),
                    value: 'productNumber',
                    defaultConfigs: {
                        searchable: true,
                        ranking: 1000,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.manufacturerNumber'),
                    value: 'manufacturerNumber',
                    defaultConfigs: {
                        searchable: true,
                        ranking: 500,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.ean'),
                    value: 'ean',
                    defaultConfigs: {
                        searchable: true,
                        ranking: 500,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.customSearchKeywords'),
                    value: 'customSearchKeywords',
                    defaultConfigs: {
                        searchable: true,
                        ranking: 800,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.manufacturerName'),
                    value: 'manufacturer.name',
                    defaultConfigs: {
                        searchable: true,
                        ranking: 500,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.manufacturerCustomFields'),
                    value: 'manufacturer.customFields',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.categoriesName'),
                    value: 'categories.name',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.categoriesCustomFields'),
                    value: 'categories.customFields',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.tagsName'),
                    value: 'tags.name',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.metaTitle'),
                    value: 'metaTitle',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.metaDescription'),
                    value: 'metaDescription',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.propertiesName'),
                    value: 'properties.name',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
                {
                    label: this.$tc('sw-settings-search.generalTab.configFields.variantValue'),
                    value: 'options.name',
                    defaultConfigs: {
                        searchable: false,
                        ranking: 0,
                        tokenize: false,
                    },
                },
            ],
        };
    },

    computed: {
        productSearchFieldRepository() {
            return this.repositoryFactory.create('product_search_config_field');
        },

        productSearchFieldCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('searchConfigId', this.searchConfigId));
            criteria.addSorting(Criteria.sort('field', 'ESC'));

            if (this.defaultTab === this.tabNames.generalTab) {
                criteria.addFilter(Criteria.equals('customFieldId', null));
            } else {
                criteria.addFilter(Criteria.not(
                    'AND',
                    [Criteria.equals('customFieldId', null)],
                ));
            }

            return criteria;
        },

        isListEmpty() {
            return !this.searchConfigFields || !this.searchConfigFields.length;
        },

        getProductSearchFieldColumns() {
            return [{
                property: 'field',
                label: 'sw-settings-search.generalTab.list.columnContent',
                inlineEdit: 'string',
                sortable: true,
                width: '250px',
            }, {
                property: 'searchable',
                label: 'sw-settings-search.generalTab.list.columnSearchable',
                align: 'center',
                inlineEdit: 'string',
                sortable: true,
            }, {
                property: 'ranking',
                inlineEdit: 'number',
                label: 'sw-settings-search.generalTab.list.columnRankingPoints',
                align: 'right',
                sortable: true,
            }, {
                property: 'tokenize',
                inlineEdit: 'string',
                label: 'sw-settings-search.generalTab.list.columnSplitKeywords',
                align: 'center',
                sortable: true,
            }];
        },

        storefrontEsEnable() {
            return Context.app.storefrontEsEnable ?? false;
        },
    },

    watch: {
        searchConfigId(newValue) {
            this.searchConfigId = newValue;
            this.loadData();
        },

        productSearchConfigs() {
            this.loadData();
        },
    },

    methods: {
        onShowExampleModal() {
            this.showExampleModal = true;
        },

        onCloseExampleModal() {
            this.showExampleModal = false;
        },

        onAddNewConfig() {
            const item = this.createNewConfigItem();
            this.searchConfigFields.unshift(item);
            this.$emit('edit-change', true);
        },

        createNewConfigItem() {
            const newItem = this.productSearchFieldRepository.create();

            newItem.searchConfigId = this.searchConfigId;
            newItem.searchable = false;
            newItem.ranking = 0;
            newItem.field = '';
            newItem.tokenize = false;

            return newItem;
        },

        getConfigFieldDefault(fieldName) {
            const configFieldMatching = this.fieldConfigs.find(fieldConfig => fieldConfig.value === fieldName);

            if (configFieldMatching) {
                return { ...configFieldMatching.defaultConfigs };
            }

            return {
                ranking: 0,
                searchable: false,
                tokenize: false,
            };
        },

        onResetToDefault() {
            const isGeneralTab = this.defaultTab === this.tabNames.generalTab;

            this.searchConfigFields.forEach((searchConfigField) => {
                searchConfigField.ranking =
                    isGeneralTab ? this.getConfigFieldDefault(searchConfigField.field).ranking : 0;
                searchConfigField.searchable =
                    isGeneralTab ? this.getConfigFieldDefault(searchConfigField.field).searchable : false;
                searchConfigField.tokenize =
                    isGeneralTab ? this.getConfigFieldDefault(searchConfigField.field).tokenize : false;

                return searchConfigField;
            });

            this.saveConfig();
        },

        onChangeTab(tabContent) {
            this.defaultTab = tabContent;
            this.loadData();
        },

        loadData() {
            this.getProductSearchFieldsList();
        },

        getProductSearchFieldsList() {
            this.isLoading = true;
            const criteria = this.productSearchFieldCriteria;

            this.productSearchFieldRepository.search(criteria)
                .then((items) => {
                    this.total = items.total;
                    this.isEnabledReset = !items.total;
                    this.searchConfigFields = items;
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.loadError'),
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        saveConfig() {
            this.isLoading = true;
            this.productSearchFieldRepository
                .saveAll(this.searchConfigFields)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-search.notification.saveSuccess'),
                    });
                    this.$emit('edit-change', false);
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError'),
                    });
                }).finally(() => {
                    this.isLoading = false;
                    this.loadData();
                });
        },

        deleteConfig(configFieldId) {
            if (!configFieldId) {
                return;
            }

            this.isLoading = true;
            this.productSearchFieldRepository.delete(configFieldId)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-search.notification.saveSuccess'),
                    });
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError'),
                    });
                }).finally(() => {
                    this.isLoading = false;
                    this.loadData();
                });
        },
    },
};
