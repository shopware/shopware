import template from './sw-settings-listing.html.twig';
import './sw-settings-listing.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-listing', {
    template,

    mixins: [
        'notification',
        'sw-inline-snippet'
    ],

    inject: ['repositoryFactory', 'systemConfigApiService', 'feature'],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            productSortingOptions: [],
            sortingOptionsGridLimit: 10,
            sortingOptionsGridPage: 1,
            modalVisible: false,
            toBeDeletedProductSortingOptionId: null,
            productSortingOptionsSearchTerm: null,
            isProductSortingOptionsCardLoading: false,
            customFields: []
        };
    },

    computed: {
        productSortingOptionRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        filteredProductSortingOptions() {
            return this.productSortingOptions.filter(option => !option.locked);
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        productSortingsOptionsCriteria() {
            const criteria = new Criteria();

            criteria
                .setLimit(this.sortingOptionsGridLimit)
                .setPage(this.sortingOptionsGridPage);

            criteria.addSorting(
                Criteria.sort('priority', 'DESC')
            );

            return criteria;
        },

        productSortingOptionsSearchCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(
                Criteria.contains('label', this.productSortingOptionsSearchTerm)
            );

            return criteria;
        },

        sortingOptionsGridTotal() {
            return this.productSortingOptions.total;
        },

        customFieldCriteria() {
            return new Criteria();
        },

        productSortingOptionColumns() {
            return [
                {
                    property: 'label',
                    routerLink: 'sw.settings.listing.edit',
                    label: this.$tc('sw-settings-listing.index.productSorting.grid.header.name')
                },
                {
                    property: 'criteria',
                    label: this.$tc('sw-settings-listing.index.productSorting.grid.header.criteria'),
                    multiLine: true
                },
                {
                    property: 'priority',
                    inlineEdit: 'number',
                    label: this.$tc('sw-settings-listing.index.productSorting.grid.header.priority')
                }
            ];
        },

        /**
         * Filtered list of products sortings that are not locked.
         * Used by the select field inside the system settings section
         * @return {[]|*[]}
         */
        notLockedProductSortings() {
            return this.productSortingOptions.filter(currentProductSorting => {
                return !currentProductSorting.locked;
            });
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        createdComponent() {
            this.fetchProductSortingOptions();
            this.fetchCustomFields();
        },

        fetchProductSortingOptions() {
            this.isProductSortingOptionsCardLoading = true;

            this.productSortingOptionRepository.search(
                this.productSortingsOptionsCriteria,
                Shopware.Context.api
            ).then(response => {
                this.productSortingOptions = response;

                this.isProductSortingOptionsCardLoading = false;
            });
        },

        fetchCustomFields() {
            this.customFieldRepository.search(this.customFieldCriteria, Shopware.Context.api).then(response => {
                this.customFields = response;
            });
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            const saveSalesChannelConfig = await this.$refs.systemConfig.saveAll();

            this.setDefaultSortingActive();

            const saveProductSortingOptions = await this.saveProductSortingOptions();

            Promise.all([saveSalesChannelConfig, saveProductSortingOptions])
                .then(() => {
                    this.isSaveSuccessful = true;

                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-listing.general.messageSaveSuccess')
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-listing.general.messageSaveError')
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveProductSortingOptions() {
            return this.productSortingOptionRepository.saveAll(this.productSortingOptions, Shopware.Context.api);
        },

        onDeleteProductSorting(id) {
            // closes modal
            this.toBeDeletedProductSortingOptionId = null;

            this.productSortingOptionRepository.delete(id, Shopware.Context.api)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-listing.index.productSorting.messageDeleteError')
                    });
                })
                .finally(() => {
                    this.fetchProductSortingOptions();
                });
        },

        onPageChange({ page = 1, limit = 10 }) {
            this.sortingOptionsGridPage = page;
            this.sortingOptionsGridLimit = limit;

            this.fetchProductSortingOptions();
        },

        onEditProductSortingOption(productSortingId) {
            this.$router.push({ name: 'sw.settings.listing.edit', params: { id: productSortingId } });
        },

        formatProductSortingOptionField(fields) {
            const labels = fields.map(currentField => {
                if (this.isItemACustomField(currentField.field)) {
                    return this.getCustomFieldLabelByCriteriaName(currentField.field);
                }

                return this.$tc(
                    `sw-settings-listing.general.productSortingCriteriaGrid.options.label.${currentField.field}`
                );
            });

            return labels.join(', ');
        },

        getCustomFieldLabelByCriteriaName(criteriaName) {
            const technicalName = this.stripCustomFieldPath(criteriaName);
            const customField = this.getCustomFieldByName(technicalName);

            return this.getInlineSnippet(customField.config.label);
        },

        getCustomFieldByName(technicalName) {
            return this.customFields.find(currentCustomField => {
                return currentCustomField.name === technicalName;
            });
        },

        onAddNewProductSortingOption() {
            this.$router.push({ name: 'sw.settings.listing.create' });
        },

        onSearchProductSortingOptions() {
            const searchTerm = this.productSortingOptionsSearchTerm;

            if (!searchTerm) {
                this.fetchProductSortingOptions();

                return;
            }

            this.productSortingOptionRepository.search(
                this.productSortingOptionsSearchCriteria,
                Shopware.Context.api
            ).then(response => {
                this.productSortingOptions = response;
            });
        },

        onSaveProductSortingOptionInlineEdit(newProductSortingOption) {
            const indexOfOldProductSortingOption = this.productSortingOptions.findIndex(currentElement => {
                return currentElement.id === newProductSortingOption.id;
            });

            this.productSortingOptions[indexOfOldProductSortingOption] = newProductSortingOption;

            this.onSave().then(() => {
                this.fetchProductSortingOptions();
            });
        },

        isItemACustomField(fieldName) {
            const strippedFieldName = this.stripCustomFieldPath(fieldName);

            return this.customFields.some(currentCustomField => {
                return currentCustomField.name === strippedFieldName;
            });
        },

        getCustomFieldById(id) {
            const customField = this.customFields.find(currentCustomField => {
                return currentCustomField.id === id;
            });

            return customField.name;
        },

        stripCustomFieldPath(fieldName) {
            return fieldName.replace(/customFields\./, '');
        },

        isProductSortingEditable(item) {
            return !item.locked;
        },

        onChangeLanguage() {
            this.fetchProductSortingOptions();
        },

        setDefaultSortingActive() {
            const defaultSortingKey = this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting'];

            if (defaultSortingKey) {
                Object.entries(this.productSortingOptions).forEach(([, productSorting]) => {
                    if (productSorting.key === defaultSortingKey) {
                        productSorting.active = true;
                    }
                });
            }
        },

        isItemDefaultSorting(sortingKey) {
            const systemSettingAvailable = !!this.$refs.systemConfig.actualConfigData.null;

            if (!systemSettingAvailable) {
                return null;
            }

            return sortingKey === this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting'];
        }
    }
});
