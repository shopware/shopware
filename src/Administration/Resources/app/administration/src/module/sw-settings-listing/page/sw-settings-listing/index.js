import template from './sw-settings-listing.html.twig';
import './sw-settings-listing.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        'notification',
        'sw-inline-snippet',
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            productSortingOptions: [],
            sortingOptionsGridLimit: 10,
            sortingOptionsGridPage: 1,
            modalVisible: false,
            toBeDeletedProductSortingOption: null,
            productSortingOptionsSearchTerm: null,
            isProductSortingOptionsCardLoading: false,
            isDefaultSalesChannelLoading: false,
            customFields: [],
        };
    },

    computed: {
        productSortingOptionRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        productSortingsOptionsCriteria() {
            const criteria = new Criteria(this.sortingOptionsGridPage, this.sortingOptionsGridLimit);

            criteria.addSorting(
                Criteria.sort('priority', 'DESC'),
            );

            criteria.addFilter(
                Criteria.equals('locked', false),
            );

            return criteria;
        },

        productSortingOptionsSearchCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.contains('label', this.productSortingOptionsSearchTerm),
            );

            return criteria;
        },

        sortingOptionsGridTotal() {
            return this.productSortingOptions.total;
        },

        customFieldCriteria() {
            return new Criteria(1, 25);
        },

        productSortingOptionColumns() {
            return [
                {
                    property: 'label',
                    routerLink: 'sw.settings.listing.edit',
                    label: this.$tc('sw-settings-listing.index.productSorting.grid.header.name'),
                },
                {
                    property: 'criteria',
                    label: this.$tc('sw-settings-listing.index.productSorting.grid.header.criteria'),
                    multiLine: true,
                },
                {
                    property: 'priority',
                    inlineEdit: 'number',
                    label: this.$tc('sw-settings-listing.index.productSorting.grid.header.priority'),
                },
            ];
        },
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

            this.productSortingOptionRepository.search(this.productSortingsOptionsCriteria).then(response => {
                this.productSortingOptions = response;

                this.isProductSortingOptionsCardLoading = false;
            });
        },

        fetchCustomFields() {
            this.customFieldRepository.search(this.customFieldCriteria).then(response => {
                this.customFields = response;
            });
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            const saveSalesChannelConfig = await this.$refs.systemConfig.saveAll();

            this.setDefaultSortingActive();

            const saveProductSortingOptions = await this.saveProductSortingOptions();

            const saveSalesChannelVisibilityConfig = await this.$refs.defaultSalesChannelCard
                .saveSalesChannelVisibilityConfig();

            Promise.all([saveSalesChannelConfig, saveProductSortingOptions, saveSalesChannelVisibilityConfig])
                .then(() => {
                    this.isSaveSuccessful = true;

                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-listing.general.messageSaveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-listing.general.messageSaveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveProductSortingOptions() {
            return this.productSortingOptionRepository.saveAll(this.productSortingOptions);
        },

        onDeleteProductSorting(item) {
            // closes modal
            this.toBeDeletedProductSortingOption = null;

            this.productSortingOptionRepository.delete(item.id)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-listing.index.productSorting.messageDeleteError'),
                    });
                })
                .finally(() => {
                    this.fetchProductSortingOptions();
                    this.checkForPagination();
                });
        },

        /**
         * check, if we need to paginate back, when deleting the last sorting option on a page
         */
        checkForPagination() {
            if (this.sortingOptionsGridPage !== 1) {
                const newTotal = this.productSortingOptions.total - 1;

                if ((this.sortingOptionsGridPage * this.sortingOptionsGridLimit) >= newTotal) {
                    this.onPageChange({
                        page: this.sortingOptionsGridPage - 1,
                        limit: this.sortingOptionsGridLimit,
                    });
                }
            }
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
            if (!Array.isArray(fields)) {
                return '';
            }

            const labels = fields.map(currentField => {
                if (this.isItemACustomField(currentField.field)) {
                    return this.getCustomFieldLabelByCriteriaName(currentField.field);
                }

                return this.$tc(
                    `sw-settings-listing.general.productSortingCriteriaGrid.options.label.${currentField.field}`,
                );
            });

            return labels.join(', ');
        },

        getCustomFieldLabelByCriteriaName(criteriaName) {
            const technicalName = this.stripCustomFieldPath(criteriaName);
            const customField = this.getCustomFieldByName(technicalName);

            const inlineSnippet = this.getInlineSnippet(customField.config.label);

            if (inlineSnippet === null) {
                return technicalName;
            }

            return inlineSnippet;
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

            this.productSortingOptionRepository.search(this.productSortingOptionsSearchCriteria).then(response => {
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
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },
    },
};
