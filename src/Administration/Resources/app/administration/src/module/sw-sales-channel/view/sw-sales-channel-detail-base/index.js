/**
 * @package sales-channel
 */

import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.scss';

const { Component, Mixin, Context, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
const domUtils = Shopware.Utils.dom;
const ShopwareError = Shopware.Classes.ShopwareError;
const utils = Shopware.Utils;

const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'salesChannelService',
        'productExportService',
        'repositoryFactory',
        'knownIpsService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        // FIXME: add type for salesChannel property
        // eslint-disable-next-line vue/require-prop-types
        salesChannel: {
            required: true,
        },

        productExport: {
            // type: Entity
            type: Object,
            required: true,
        },

        // FIXME: add default value for this property
        // eslint-disable-next-line vue/require-default-prop
        storefrontSalesChannelCriteria: {
            type: Criteria,
            required: false,
        },

        customFieldSets: {
            type: Array,
            required: true,
        },

        isLoading: {
            type: Boolean,
            default: false,
        },

        productComparisonAccessUrl: {
            type: String,
            default: '',
        },

        templateOptions: {
            type: Array,
            default: () => [],
        },

        showTemplateModal: {
            type: Boolean,
            default: false,
        },

        templateName: {
            type: String,
            default: null,
        },
    },

    data() {
        return {
            showDeleteModal: false,
            defaultSnippetSetId: '71a916e745114d72abafbfdc51cbd9d0',
            isLoadingDomains: false,
            deleteDomain: null,
            storefrontDomains: [],
            selectedStorefrontSalesChannel: null,
            invalidFileName: false,
            isFileNameChecking: false,
            disableGenerateByCronjob: false,
            knownIps: [],
            mainCategoriesCollection: null,
            footerCategoriesCollection: null,
            serviceCategoriesCollection: null,
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        isStoreFront() {
            return this.salesChannel && this.salesChannel.typeId === Defaults.storefrontSalesChannelTypeId;
        },

        isDomainAware() {
            const domainAware = [Defaults.storefrontSalesChannelTypeId, Defaults.apiSalesChannelTypeId];
            return domainAware.includes(this.salesChannel.typeId);
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        isProductComparison() {
            return this.salesChannel && this.salesChannel.typeId === Defaults.productComparisonTypeId;
        },

        storefrontSalesChannelDomainCriteria() {
            const criteria = new Criteria(1, 25);

            return criteria.addFilter(Criteria.equals('salesChannelId', this.productExport.storefrontSalesChannelId));
        },

        storefrontSalesChannelCurrencyCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('salesChannels');

            return criteria.addFilter(Criteria.equals('salesChannels.id', this.productExport.storefrontSalesChannelId));
        },

        paymentMethodCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        countryCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        disabledCountries() {
            return this.salesChannel?.countries?.filter(country => country.active === false) ?? [];
        },

        disabledCountryVariant() {
            return this.disabledCountries
                .find(country => country.id === this.salesChannel.countryId) ? 'warning' : 'info';
        },

        disabledPaymentMethods() {
            return this.salesChannel?.paymentMethods?.filter(paymentMethod => paymentMethod.active === false) ?? [];
        },

        disabledPaymentMethodVariant() {
            return this.disabledPaymentMethods
                .find(paymentMethod => paymentMethod.id === this.salesChannel.paymentMethodId) ? 'warning' : 'info';
        },

        disabledShippingMethods() {
            return this.salesChannel?.shippingMethods?.filter(shippingMethod => shippingMethod.active === false) ?? [];
        },

        disabledShippingMethodVariant() {
            return this.disabledShippingMethods
                .find(shippingMethod => shippingMethod.id === this.salesChannel.shippingMethodId) ? 'warning' : 'info';
        },

        unservedLanguages() {
            return this.salesChannel.languages?.filter(
                language => (this.salesChannel.domains?.filter(
                    domain => domain.languageId === language.id,
                ) || []).length === 0,
            ) ?? [];
        },

        unservedLanguageVariant() {
            return this.unservedLanguages
                .find(language => language.id === this.salesChannel.languageId) ? 'warning' : 'info';
        },

        storefrontDomainsLoaded() {
            return this.storefrontDomains.length > 0;
        },

        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source,
            );
        },

        globalDomainRepository() {
            return this.repositoryFactory.create('sales_channel_domain');
        },

        productExportRepository() {
            return this.repositoryFactory.create('product_export');
        },

        mainNavigationCriteria() {
            const criteria = new Criteria(1, 10);
            return criteria
                .addFilter(Criteria.equalsAny('type', ['page', 'folder']));
        },

        getIntervalOptions() {
            return [
                {
                    id: 0,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.0'),
                },
                {
                    id: 120,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.120'),
                },
                {
                    id: 300,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.300'),
                },
                {
                    id: 600,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.600'),
                },
                {
                    id: 900,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.900'),
                },
                {
                    id: 1800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.1800'),
                },
                {
                    id: 3600,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.3600'),
                },
                {
                    id: 7200,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.7200'),
                },
                {
                    id: 14400,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.14400'),
                },
                {
                    id: 28800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.28800'),
                },
                {
                    id: 43200,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.43200'),
                },
                {
                    id: 86400,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.86400'),
                },
                {
                    id: 172800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.172800'),
                },
                {
                    id: 259200,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.259200'),
                },
                {
                    id: 345600,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.345600'),
                },
                {
                    id: 432000,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.432000'),
                },
                {
                    id: 518400,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.518400'),
                },
                {
                    id: 604800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.604800'),
                },
            ];
        },

        getFileFormatOptions() {
            return [
                {
                    id: 'csv',
                    name: this.$tc('sw-sales-channel.detail.productComparison.fileFormatLabels.csv'),
                },
                {
                    id: 'xml',
                    name: this.$tc('sw-sales-channel.detail.productComparison.fileFormatLabels.xml'),
                },
            ];
        },

        getEncodingOptions() {
            return [
                {
                    id: 'ISO-8859-1',
                    name: 'ISO-8859-1',
                },
                {
                    id: 'UTF-8',
                    name: 'UTF-8',
                },
            ];
        },

        invalidFileNameError() {
            if (this.invalidFileName && !this.isFileNameChecking) {
                this.$emit('invalid-file-name');
                return new ShopwareError({ code: 'DUPLICATED_PRODUCT_EXPORT_FILE_NAME' });
            }

            this.$emit('valid-file-name');
            return null;
        },

        helpTextTaxCalculation() {
            const link = {
                name: 'sw.settings.tax.index',
            };

            return this.$tc('sw-sales-channel.detail.helpTextTaxCalculation.label', 0, {
                link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$tc('sw-sales-channel.detail.helpTextTaxCalculation.linkText')}
                      </sw-internal-link>`,
            });
        },

        taxCalculationTypeOptions() {
            return [
                {
                    value: 'horizontal',
                    name: this.$tc('sw-sales-channel.detail.taxCalculation.horizontalName'),
                    description: this.$tc('sw-sales-channel.detail.taxCalculation.horizontalDescription'),
                },
                {
                    value: 'vertical',
                    name: this.$tc('sw-sales-channel.detail.taxCalculation.verticalName'),
                    description: this.$tc('sw-sales-channel.detail.taxCalculation.verticalDescription'),
                },
            ];
        },

        /**
         * @deprecated tag:v6.6.0 will be removed. use maintenanceIpAllowlist instead
         */
        // eslint-disable-next-line inclusive-language/use-inclusive-words
        maintenanceIpWhitelist: {
            get() {
                return this.maintenanceIpAllowlist;
            },
            set(value) {
                this.maintenanceIpAllowlist = value;
            },
        },

        maintenanceIpAllowlist: {
            get() {
                // eslint-disable-next-line inclusive-language/use-inclusive-words
                return this.salesChannel.maintenanceIpWhitelist ? this.salesChannel.maintenanceIpWhitelist : [];
            },
            set(value) {
                // eslint-disable-next-line inclusive-language/use-inclusive-words
                this.salesChannel.maintenanceIpWhitelist = value;
            },
        },

        ...mapPropertyErrors(
            'salesChannel',
            [
                'name',
                'customerGroupId',
                'navigationCategoryId',
            ],
        ),

        ...mapPropertyErrors(
            'productExport',
            [
                'productStreamId',
                'encoding',
                'fileName',
                'fileFormat',
                'salesChannelDomainId',
                'currencyId',
            ],
        ),

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        mainCategoryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.salesChannel.navigationCategoryId || null));

            return criteria;
        },

        footerCategoryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.salesChannel.footerCategoryId || null));

            return criteria;
        },

        serviceCategoryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.salesChannel.serviceCategoryId || null));

            return criteria;
        },

        mainCategories() {
            return this.mainCategoriesCollection ? this.mainCategoriesCollection : [];
        },

        footerCategories() {
            return this.footerCategoriesCollection ? this.footerCategoriesCollection : [];
        },

        serviceCategories() {
            return this.serviceCategoriesCollection ? this.serviceCategoriesCollection : [];
        },

        navigationCategoryPlaceholder() {
            return this.salesChannel.navigationCategoryId ? '' : this.$tc('sw-category.base.link.categoryPlaceholder');
        },

        footerCategoryPlaceholder() {
            return this.salesChannel.footerCategoryId ? '' : this.$tc('sw-category.base.link.categoryPlaceholder');
        },

        serviceCategoryPlaceholder() {
            return this.salesChannel.serviceCategoryId ? '' : this.$tc('sw-category.base.link.categoryPlaceholder');
        },

        salesChannelFavoritesService() {
            return Shopware.Service('salesChannelFavorites');
        },

        currencyCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },
    },

    watch: {
        'productExport.fileName'() {
            this.onChangeFileName();
        },
        salesChannel() {
            this.createCategoryCollections();
        },
    },

    created() {
        this.knownIpsService.getKnownIps().then(ips => {
            this.knownIps = ips;
        });

        this.createCategoryCollections();
    },

    methods: {
        onGenerateKeys() {
            this.salesChannelService.generateKey().then((response) => {
                this.salesChannel.accessKey = response.accessKey;
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-sales-channel.detail.messageAPIError'),
                });
            });
        },

        onGenerateProductExportKey(displaySaveNotification = true) {
            this.productExportService.generateKey().then((response) => {
                this.productExport.accessKey = response.accessKey;
                this.$emit('access-key-changed');

                if (displaySaveNotification) {
                    this.createNotificationInfo({
                        message: this.$tc('sw-sales-channel.detail.productComparison.messageAccessKeyChanged'),
                    });
                }
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-sales-channel.detail.messageAPIError'),
                });
            });
        },

        onToggleActive() {
            if (this.salesChannel.active !== true || this.isProductComparison) {
                return;
            }

            const criteria = new Criteria(1, 25);
            criteria.addAssociation('themes');

            this.salesChannelRepository
                .get(this.$route.params.id, Context.api, criteria)
                .then((entity) => {
                    if (entity.extensions.themes !== undefined && entity.extensions.themes.length >= 1) {
                        return;
                    }

                    this.salesChannel.active = false;
                    this.createNotificationError({
                        message: this.$tc('sw-sales-channel.detail.messageActivateWithoutThemeError', 0, {
                            name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name'),
                        }),
                    });
                });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.showDeleteModal = false;

            this.$nextTick(() => {
                this.deleteSalesChannel(this.salesChannel.id);
                this.$router.push({ name: 'sw.dashboard.index' });
            });
        },

        deleteSalesChannel(salesChannelId) {
            this.salesChannelRepository.delete(salesChannelId, Context.api).then(() => {
                this.$root.$emit('sales-channel-change');
                this.salesChannelFavoritesService.refresh();
            });
        },

        copyToClipboard() {
            domUtils.copyToClipboard(this.salesChannel.accessKey);
        },

        onStorefrontSelectionChange(storefrontSalesChannelId) {
            this.salesChannelRepository.get(storefrontSalesChannelId)
                .then((entity) => {
                    this.salesChannel.languageId = entity.languageId;
                    this.salesChannel.currencyId = entity.currencyId;
                    this.salesChannel.paymentMethodId = entity.paymentMethodId;
                    this.salesChannel.shippingMethodId = entity.shippingMethodId;
                    this.salesChannel.countryId = entity.countryId;
                    this.salesChannel.navigationCategoryId = entity.navigationCategoryId;
                    this.salesChannel.navigationCategoryVersionId = entity.navigationCategoryVersionId;
                    this.salesChannel.customerGroupId = entity.customerGroupId;
                });
        },

        onStorefrontDomainSelectionChange(storefrontSalesChannelDomainId) {
            this.globalDomainRepository.get(storefrontSalesChannelDomainId)
                .then((entity) => {
                    this.productExport.salesChannelDomain = entity;
                    this.productExport.currencyId = entity.currencyId;
                    this.$emit('domain-changed');
                });
        },

        loadStorefrontDomains(storefrontSalesChannelId) {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('salesChannelId', storefrontSalesChannelId));

            this.globalDomainRepository.search(criteria)
                .then((searchResult) => {
                    this.storefrontDomains = searchResult;
                });
        },

        onChangeFileName() {
            this.isFileNameChecking = true;
            this.onChangeFileNameDebounce();
        },

        onChangeFileNameDebounce: utils.debounce(function executeChange() {
            if (!this.productExport) {
                return;
            }

            if (typeof this.productExport.fileName !== 'string' ||
                this.productExport.fileName.trim() === ''
            ) {
                this.invalidFileName = false;
                this.isFileNameChecking = false;
                return;
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(
                Criteria.multi(
                    'AND',
                    [
                        Criteria.equals('fileName', this.productExport.fileName),
                        Criteria.not('AND', [Criteria.equals('id', this.productExport.id)]),
                    ],
                ),
            );

            this.productExportRepository.search(criteria).then(({ total }) => {
                this.invalidFileName = total > 0;
                this.isFileNameChecking = false;
            }).catch(() => {
                this.invalidFileName = true;
                this.isFileNameChecking = false;
            });
        }, 500),

        changeInterval() {
            this.disableGenerateByCronjob = this.productExport.interval === 0;

            if (this.disableGenerateByCronjob) {
                this.productExport.generateByCronjob = false;
            }
        },

        createCategoryCollections() {
            if (!this.salesChannel) {
                return;
            }

            this.createCategoriesCollection(this.mainCategoryCriteria, 'mainCategoriesCollection');
            this.createCategoriesCollection(this.footerCategoryCriteria, 'footerCategoriesCollection');
            this.createCategoriesCollection(this.serviceCategoryCriteria, 'serviceCategoriesCollection');
        },

        async createCategoriesCollection(criteria, collectionName) {
            this[collectionName] = await this.categoryRepository.search(criteria, Shopware.Context.api);
        },

        onMainSelectionAdd(item) {
            this.salesChannel.navigationCategoryId = item.id;
        },

        onMainSelectionRemove() {
            this.salesChannel.navigationCategoryId = null;
        },

        onFooterSelectionAdd(item) {
            this.salesChannel.footerCategoryId = item.id;
        },

        onFooterSelectionRemove() {
            this.salesChannel.footerCategoryId = null;
        },

        onServiceSelectionAdd(item) {
            this.salesChannel.serviceCategoryId = item.id;
        },

        onServiceSelectionRemove() {
            this.salesChannel.serviceCategoryId = null;
        },

        buildDisabledPaymentAlert(snippet, collection, property = 'name') {
            const route = { name: 'sw.settings.payment.overview' };
            const routeData = this.$router.resolve(route);

            const data = {
                separatedList: collection.map((item) => (
                    `<span>${item.translated[property].replaceAll('|', '&vert;')}</span>`
                )).join(', '),
                paymentSettingsLink: routeData.href,
            };

            return this.$tc(snippet, collection.length, data);
        },

        buildDisabledShippingAlert(snippet, collection, property = 'name') {
            const data = {
                name: collection.first().translated[property].replaceAll('|', '&vert;'),
                addition: collection.length > 2
                    ? this.$tc('sw-sales-channel.detail.warningDisabledAddition', 1, { amount: collection.length - 1 })
                    : collection.last().translated[property].replaceAll('|', '&vert;'),
            };

            return this.$tc(snippet, collection.length, data);
        },

        buildUnservedLanguagesAlert(snippet, collection, property = 'name') {
            const data = {
                list: collection.map((item) => item[property]).join(', '),
            };

            return this.$tc(snippet, collection.length, data);
        },

        isFavorite() {
            return this.salesChannelFavoritesService.isFavorite(this.salesChannel.id);
        },
    },
};
