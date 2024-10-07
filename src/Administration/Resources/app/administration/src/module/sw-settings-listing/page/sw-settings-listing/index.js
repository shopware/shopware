/**
 * @package inventory
 */
import template from './sw-settings-listing.html.twig';
import './sw-settings-listing.scss';

const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
    ],

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
            hasDefaultSortingError: false,
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

        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        productSortingsOptionsCriteria() {
            const criteria = new Criteria(this.sortingOptionsGridPage, this.sortingOptionsGridLimit);

            criteria.addSorting(Criteria.sort('priority', 'DESC'));

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        productSortingOptionsSearchCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.contains('label', this.productSortingOptionsSearchTerm));

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

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        salesChannelDefaultSortingError() {
            const code = this.$refs.systemConfig.isNotDefaultSalesChannel
                ? 'PARENT_MUST_NOT_BE_EMPTY'
                : 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

            return new ShopwareError({
                code,
            });
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

            this.productSortingOptionRepository.search(this.productSortingsOptionsCriteria).then((response) => {
                this.productSortingOptions = response;

                this.isProductSortingOptionsCardLoading = false;
            });
        },

        fetchCustomFields() {
            this.customFieldRepository.search(this.customFieldCriteria).then((response) => {
                this.customFields = response;
            });
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.hasDefaultSortingError = false;

            const validateSalesChannelDefaultSortingOption = new Promise((resolve, reject) => {
                if (!this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting']) {
                    this.hasDefaultSortingError = true;
                    reject();
                }
                resolve();
            });

            return validateSalesChannelDefaultSortingOption
                .then(async () => {
                    const saveSalesChannelConfig = this.$refs.systemConfig.saveAll();

                    this.setDefaultSortingActive();

                    const saveProductSortingOptions = this.saveProductSortingOptions();

                    const saveSalesChannelVisibilityConfig =
                        this.$refs.defaultSalesChannelCard.saveSalesChannelVisibilityConfig();

                    return Promise.all([
                        saveSalesChannelConfig,
                        saveProductSortingOptions,
                        saveSalesChannelVisibilityConfig,
                    ]);
                })
                .then(() => {
                    this.isSaveSuccessful = true;

                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-listing.general.messageSaveSuccess'),
                    });
                })
                .catch((e) => {
                    const options = {
                        message: e?.response.data?.errors[0]?.detail || 'Unknown error',
                    };
                    this.createNotificationError({
                        message: this.$tc('sw-settings-listing.general.messageSaveError', options),
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
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('configurationKey', 'core.listing.defaultSorting'));
            criteria.addFilter(Criteria.equals('configurationValue', item.id));

            this.systemConfigRepository.search(criteria).then((result) => {
                const actualConfigData = {};
                result.forEach((entry) => {
                    actualConfigData[entry.salesChannelId] = {
                        'core.listing.defaultSorting': null,
                    };
                });
                // cannot delete the entries directly via the systemConfigRepository, because Rufus blocks write access
                this.systemConfigApiService.batchSave(actualConfigData);
            });

            Object.keys(this.$refs.systemConfig.actualConfigData).forEach((id) => {
                const configData = this.$refs.systemConfig.actualConfigData[id];
                if (configData && configData['core.listing.defaultSorting'] === item.id) {
                    configData['core.listing.defaultSorting'] = null;
                }
            });

            // closes modal
            this.toBeDeletedProductSortingOption = null;

            this.productSortingOptionRepository
                .delete(item.id)
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

                if (this.sortingOptionsGridPage * this.sortingOptionsGridLimit >= newTotal) {
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
            this.$router.push({
                name: 'sw.settings.listing.edit',
                params: { id: productSortingId },
            });
        },

        formatProductSortingOptionField(fields) {
            if (!Array.isArray(fields)) {
                return '';
            }

            const labels = fields.map((currentField) => {
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
            return this.customFields.find((currentCustomField) => {
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

            this.productSortingOptionRepository.search(this.productSortingOptionsSearchCriteria).then((response) => {
                this.productSortingOptions = response;
            });
        },

        onSaveProductSortingOptionInlineEdit(newProductSortingOption) {
            const indexOfOldProductSortingOption = this.productSortingOptions.findIndex((currentElement) => {
                return currentElement.id === newProductSortingOption.id;
            });

            this.productSortingOptions[indexOfOldProductSortingOption] = newProductSortingOption;

            this.onSave().then(() => {
                this.fetchProductSortingOptions();
            });
        },

        isItemACustomField(fieldName) {
            const strippedFieldName = this.stripCustomFieldPath(fieldName);

            return this.customFields.some((currentCustomField) => {
                return currentCustomField.name === strippedFieldName;
            });
        },

        getCustomFieldById(id) {
            const customField = this.customFields.find((currentCustomField) => {
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
            const defaultSortingId = this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting'];

            if (defaultSortingId) {
                Object.entries(this.productSortingOptions).forEach(
                    ([
                        ,
                        productSorting,
                    ]) => {
                        if (productSorting.id === defaultSortingId) {
                            productSorting.active = true;
                        }
                    },
                );
            }
        },

        isItemDefaultSorting(sortingId) {
            const systemSettingAvailable = !!this.$refs.systemConfig.actualConfigData.null;

            if (!systemSettingAvailable) {
                return null;
            }

            return sortingId === this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting'];
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },
    },
};
