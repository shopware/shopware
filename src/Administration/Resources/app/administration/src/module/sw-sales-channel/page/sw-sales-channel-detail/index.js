/**
 * @sw-package discovery
 */

import EntityValidationService from 'src/app/service/entity-validation.service';
import template from './sw-sales-channel-detail.html.twig';
import readDeprecatedComputed from '../../helper/read-deprecated-computed';
import './sw-sales-channel-detail.scss';

const { Mixin, Context, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
const objectHelper = Shopware.Utils.object;
const ShopwareError = Shopware.Classes.ShopwareError;

const REQUIRED_BASE_FIELDS = [
    'name',
    'customerGroupId',
    'currencyId',
    'languageId',
    'paymentMethodId',
    'shippingMethodId',
    'countryId',
    'navigationCategoryId',
];

const REQUIRED_PRODUCT_EXPORT_FIELDS = ['name'];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'exportTemplateService',
        'systemConfigApiService',
        'acl',
        'feature',
        'customFieldDataProviderService',
    ],

    provide() {
        return {
            /** @deprecated tag:v6.8.0 - Will be removed */
            swSalesChannelDetailGetAgenticCommerceExportConfig: () => this.agenticCommerceExportConfig,
        };
    },

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
    },

    data() {
        return {
            salesChannel: null,
            isLoading: false,
            customFieldSets: [],
            isSaveSuccessful: false,
            productComparison: {
                newProductExport: null,
                productComparisonAccessUrl: null,
                invalidFileName: false,
                templateOptions: [],
                templates: null,
                templateName: null,
                previousTemplateName: null,
                showTemplateModal: false,
                selectedTemplate: null,
            },
            /** @deprecated tag:v6.8.0 - Will be removed */
            agenticCommerceExportConfig: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.salesChannel, 'name');
        },

        productExport() {
            if (this.salesChannel && this.salesChannel.productExports.first()) {
                return this.salesChannel.productExports.first();
            }

            if (this.productComparison.newProductExport) {
                return this.productComparison.newProductExport;
            }

            this.productComparison.newProductExport = this.productExportRepository.create();
            this.productComparison.newProductExport.interval = 0;
            this.productComparison.newProductExport.generateByCronjob = false;

            return this.productComparison.newProductExport;
        },

        isStorefront() {
            if (!this.salesChannel) {
                return this.$route.params.typeId === Defaults.storefrontSalesChannelTypeId;
            }

            return this.salesChannel.typeId === Defaults.storefrontSalesChannelTypeId;
        },

        isProductComparison() {
            if (!this.salesChannel) {
                return this.$route.params.typeId === Defaults.productComparisonTypeId;
            }

            return this.salesChannel.typeId === Defaults.productComparisonTypeId;
        },

        isHeadless() {
            if (!this.salesChannel) {
                return this.$route.params.typeId === Defaults.apiSalesChannelTypeId;
            }

            return this.salesChannel.typeId === Defaults.apiSalesChannelTypeId;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        isAgenticCommerce() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.isAgenticCommerce is deprecated. Will be removed.',
            );

            if (!this.salesChannel) {
                return this.$route.params.typeId === Defaults.agenticCommerceTypeId;
            }

            return this.salesChannel.typeId === Defaults.agenticCommerceTypeId;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        hasSwagAgenticCommercePlugin() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.hasSwagAgenticCommercePlugin is deprecated. Will be removed.',
            );

            return !!Shopware.Context.app.config.bundles?.SwagAgenticCommerce;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        showAgenticCommerceDeprecationBanner() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.showAgenticCommerceDeprecationBanner is deprecated. Will be removed.',
            );

            return this.isAgenticCommerceDeprecated && !this.hasSwagAgenticCommercePluginDeprecated;
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `isAgenticCommerce`
         *
         * Reads `isAgenticCommerce` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Returns `false` with the 6.8 flag, as if the member were removed.
         */
        isAgenticCommerceDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return false;
            }

            return readDeprecatedComputed(this, 'isAgenticCommerce');
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `hasSwagAgenticCommercePlugin`
         *
         * Reads `hasSwagAgenticCommercePlugin` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Returns `false` with the 6.8 flag, as if the member were removed.
         */
        hasSwagAgenticCommercePluginDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return false;
            }

            return readDeprecatedComputed(this, 'hasSwagAgenticCommercePlugin');
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `showAgenticCommerceDeprecationBanner`
         *
         * Reads `showAgenticCommerceDeprecationBanner` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Returns `false` with the 6.8 flag, as if the member were removed.
         */
        showAgenticCommerceDeprecationBannerDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return false;
            }

            return readDeprecatedComputed(this, 'showAgenticCommerceDeprecationBanner');
        },

        isProductExportChannel() {
            return this.isProductComparison || this.isAgenticCommerceDeprecated;
        },

        salesChannelDetailTabs() {
            const createRouteTab = (label, routeName, additionalProperties = {}) => {
                const route = {
                    name: routeName,
                    params: { id: this.$route.params.id },
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    ...additionalProperties,
                    onClick: () => {
                        void this.$router.push(route);
                    },
                };
            };

            const tabs = [createRouteTab('sw-sales-channel.detail.tabBase', 'sw.sales.channel.detail.base')];

            if (this.isAgenticCommerceDeprecated && !this.isLoading) {
                tabs.push(
                    createRouteTab(
                        'sw-sales-channel.detail.productExport.tabInsights',
                        'sw.sales.channel.detail.productExportInsights',
                    ),
                );
            }

            if (this.isHeadless || this.isStorefront) {
                tabs.push(createRouteTab('sw-sales-channel.detail.tabProducts', 'sw.sales.channel.detail.products'));
            }

            if (!this.isProductExportChannel) {
                tabs.push(
                    createRouteTab('sw-sales-channel.detail.tabTheme', 'sw.sales.channel.detail.theme', {
                        disabled: this.isLoading,
                    }),
                );
            }

            if (this.isAgenticCommerceDeprecated && !this.isLoading) {
                tabs.push(
                    createRouteTab(
                        'sw-sales-channel.detail.agenticCommerce.tabIntegration',
                        'sw.sales.channel.detail.agenticCommerceIntegration',
                    ),
                );
            }

            if (this.isProductExportChannel && !this.isLoading) {
                tabs.push(
                    createRouteTab(
                        'sw-sales-channel.detail.tabProductComparison',
                        'sw.sales.channel.detail.productComparison',
                    ),
                );
            }

            if (this.isStorefront) {
                tabs.push(createRouteTab('sw-sales-channel.detail.tabAnalytics', 'sw.sales.channel.detail.analytics'));
            }

            if (this.isHeadless || this.isStorefront) {
                tabs.push(createRouteTab('sw-sales-channel.detail.tabAgenticFiles', 'sw.sales.channel.detail.agenticFiles'));
            }

            return tabs;
        },

        salesChannelRepository() {
            // Sync keeps removed language mappings and the new languageId in one write, so the
            // default language validation sees the post-write state instead of rejecting the
            // removal of the previous default.
            return this.repositoryFactory.create('sales_channel', null, { useSync: true });
        },

        salesChannelAnalyticsRepository() {
            return this.repositoryFactory.create('sales_channel_analytics');
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldRepository() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.customFieldRepository is deprecated. Use customFieldDataProviderService instead.',
            );

            return this.repositoryFactory.create('custom_field_set');
        },

        productExportRepository() {
            return this.repositoryFactory.create('product_export');
        },

        storefrontSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            return criteria.addFilter(
                Criteria.equalsAny('typeId', [Defaults.storefrontSalesChannelTypeId, Defaults.apiSalesChannelTypeId]),
            );
        },

        tooltipSave() {
            if (!this.allowSaving) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSaving,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        allowSaving() {
            return this.acl.can('sales_channel.editor');
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        defaultAgenticCommerceExportConfig() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.defaultAgenticCommerceExportConfig is deprecated. Will be removed.',
            );

            return [
                {
                    provider: 'open-ai',
                    systemConfigDomain: 'core.openAiProductExport',
                    titleSnippet: 'sw-sales-channel.detail.agenticCommerce.openAiSettingsTitle',
                    positionIdentifier: 'sw-sales-channel-detail-base-agentic-commerce-export-config-provider',
                },
                {
                    provider: 'google',
                    systemConfigDomain: 'core.googleProductExport',
                    titleSnippet: 'sw-sales-channel.detail.agenticCommerce.googleSettingsTitle',
                    positionIdentifier: 'sw-sales-channel-detail-base-agentic-commerce-export-config-provider',
                },
            ];
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `defaultAgenticCommerceExportConfig`
         *
         * Reads `defaultAgenticCommerceExportConfig` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Returns no entries with the 6.8 flag, as if the member were removed.
         */
        defaultAgenticCommerceExportConfigDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return [];
            }

            return readDeprecatedComputed(this, 'defaultAgenticCommerceExportConfig');
        },
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },

        salesChannel: {
            deep: true,
            handler() {
                this.clearResolvedRequiredSalesChannelFieldErrors();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /** @deprecated tag:v6.8.0 - Will be removed */
        onClickInstallAgenticCommercePlugin() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.onClickInstallAgenticCommercePlugin() is deprecated. Will be removed.',
            );

            if (this.$router.hasRoute('sw.extension.store.detail')) {
                this.$router.push({ name: 'sw.extension.store.detail', params: { id: '21761' } });
                return;
            }

            this.$router.push({ name: 'sw.extension.store.landing-page' });
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `onClickInstallAgenticCommercePlugin`
         *
         * Calls `onClickInstallAgenticCommercePlugin` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Does nothing with the 6.8 flag, as if the member were removed.
         */
        onClickInstallAgenticCommercePluginDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return;
            }

            Shopware.Feature.silence('V6_8_0_0', () => this.onClickInstallAgenticCommercePlugin());
        },

        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-sales-channel-detail__salesChannel',
                path: 'salesChannel',
                scope: this,
            });
            this.loadEntityData();
            this.loadProductExportTemplates();
        },

        loadEntityData() {
            const hasRouteId = Boolean(this.$route.params.id);
            const hasRouteTypeId = Boolean(this.$route.params.typeId);

            if (!hasRouteId && hasRouteTypeId && this.salesChannel?.id) {
                this.loadAgenticCommerceExportConfigDeprecated();
                return;
            }

            if (!hasRouteId) {
                return;
            }

            if (hasRouteTypeId) {
                this.loadAgenticCommerceExportConfigDeprecated();
                return;
            }

            if (this.salesChannel) {
                this.salesChannel = null;
            }

            this.loadSalesChannel();
            this.loadCustomFieldSets();
        },

        loadSalesChannel() {
            this.isLoading = true;
            this.salesChannelRepository
                .get(this.$route.params.id.toLowerCase(), Context.api, this.getLoadSalesChannelCriteria())
                .then((entity) => {
                    this.salesChannel = entity;

                    if (!this.salesChannel.maintenanceIpAllowlist) {
                        this.salesChannel.maintenanceIpAllowlist = [];
                    }

                    this.generateAccessUrl();
                    this.loadAgenticCommerceExportConfigDeprecated();
                    this.detectCurrentTemplate();

                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.getAssociation('paymentMethods').addSorting(Criteria.sort('distinguishableName', 'ASC'));
            criteria.getAssociation('shippingMethods').addSorting(Criteria.sort('name', 'ASC'));
            criteria.getAssociation('countries').addSorting(Criteria.sort('name', 'ASC'));
            criteria.getAssociation('currencies').addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('domains');
            criteria
                .getAssociation('languages')
                .addSorting(Criteria.sort('name', 'ASC'))
                .addFilter(Criteria.equals('active', true));
            criteria.addAssociation('analytics');
            criteria.addAssociation('salesChannelFiles');

            criteria.addAssociation('productExports');
            criteria.addAssociation('productExports.salesChannelDomain.salesChannel');

            criteria.getAssociation('domains.language').addSorting(Criteria.sort('name', 'ASC'));
            criteria.getAssociation('domains.snippetSet').addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('domains.currency');
            criteria.addAssociation('domains.productExports');

            return criteria;
        },

        onTemplateSelected(templateName) {
            if (this.productComparison.templates === null || this.productComparison.templates[templateName] === undefined) {
                return;
            }

            this.productComparison.selectedTemplate = { ...this.productComparison.templates[templateName] };
            const contentChanged = Object.keys(this.productComparison.selectedTemplate).some((value) => {
                return this.productExport[value] !== this.productComparison.selectedTemplate[value];
            });

            if (!contentChanged) {
                this.productComparison.templateName = templateName;
                return;
            }

            this.productComparison.previousTemplateName = this.productComparison.templateName;
            this.productComparison.templateName = templateName;
            this.productComparison.showTemplateModal = true;
        },

        onTemplateModalClose() {
            this.productComparison.selectedTemplate = null;
            this.productComparison.templateName = this.productComparison.previousTemplateName ?? null;
            this.productComparison.previousTemplateName = null;
            this.productComparison.showTemplateModal = false;
        },

        onTemplateModalConfirm() {
            const selectedTemplate = this.productComparison.selectedTemplate;

            Object.keys(selectedTemplate).forEach((key) => {
                if (key === 'providerName') {
                    this.productExport.provider = selectedTemplate[key];
                    return;
                }

                this.productExport[key] = selectedTemplate[key];
            });

            this.productComparison.selectedTemplate = null;
            this.productComparison.previousTemplateName = null;
            this.productComparison.showTemplateModal = false;

            this.createNotificationInfo({
                message: this.$t('sw-sales-channel.detail.productComparison.templates.message.template-applied-message'),
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('sales_channel', false, 100).then((searchResult) => {
                this.customFieldSets = searchResult;
            });
        },

        generateAccessUrl() {
            if (!this.productExport.salesChannelDomain) {
                this.productComparison.productComparisonAccessUrl = '';
                return;
            }

            const domainUrl = this.productExport.salesChannelDomain.url.replace(/\/+$/g, '');
            this.productComparison.productComparisonAccessUrl = `${domainUrl}/store-api/product-export/${this.productExport.accessKey}/${this.productExport.fileName}`;
        },

        loadProductExportTemplates() {
            this.productComparison.templateOptions = Object.values(
                this.exportTemplateService.getProductExportTemplateRegistry(),
            );
            this.productComparison.templates = this.exportTemplateService.getProductExportTemplateRegistry();
        },

        detectCurrentTemplate() {
            if (!this.productComparison.templates || !this.productExport) {
                return;
            }

            const matchedTemplate = this.productComparison.templateOptions.find((template) => {
                return template.bodyTemplate !== undefined && template.bodyTemplate === this.productExport.bodyTemplate;
            });

            if (matchedTemplate) {
                this.productComparison.templateName = matchedTemplate.name;
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        setInvalidFileName(invalidFileName) {
            this.productComparison.invalidFileName = invalidFileName;
        },

        prepareSaveData() {
            const needsProductExport = this.isProductExportChannel;

            if (needsProductExport && !this.salesChannel.productExports.length) {
                this.salesChannel.productExports.add(this.productExport);
            }

            return this.updateAnalytics();
        },

        async saveSalesChannel() {
            this.isLoading = true;
            this.isSaveSuccessful = false;
            const analyticsId = this.prepareSaveData();

            try {
                await this.salesChannelRepository.save(this.salesChannel, Context.api);

                if (analyticsId && !this.salesChannel?.analytics?.trackingId) {
                    await this.salesChannelAnalyticsRepository.delete(analyticsId, Context.api);
                }

                this.isSaveSuccessful = true;

                Shopware.Utils.EventBus.emit('sw-sales-channel-detail-sales-channel-change');
            } catch (_error) {
                this.createNotificationError({
                    message: this.$t(
                        'sw-sales-channel.detail.messageSaveError',
                        {
                            name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name'),
                        },
                        0,
                    ),
                });

                this.isLoading = false;

                return false;
            }

            this.isLoading = false;

            return true;
        },

        async onSave() {
            if (!this.validateRequiredSalesChannelFields()) {
                return;
            }

            if (!this.validateAgenticCommerceExportConfigDeprecated()) {
                this.isLoading = false;
                return;
            }

            const saveSuccessful = await this.saveSalesChannel();

            if (!saveSuccessful) {
                return;
            }

            const configSaveSuccessful = await this.saveAgenticCommerceExportConfigDeprecated();

            if (!configSaveSuccessful) {
                return;
            }

            this.loadEntityData();
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `validateAgenticCommerceExportConfig`
         *
         * Calls `validateAgenticCommerceExportConfig` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Reports a valid config with the 6.8 flag, as if the member were removed.
         */
        validateAgenticCommerceExportConfigDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return true;
            }

            return Shopware.Feature.silence('V6_8_0_0', () => this.validateAgenticCommerceExportConfig());
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `loadAgenticCommerceExportConfig`
         *
         * Calls `loadAgenticCommerceExportConfig` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Does nothing with the 6.8 flag, as if the member were removed.
         */
        loadAgenticCommerceExportConfigDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return Promise.resolve();
            }

            // `silence()` rejects a returned promise, and only the synchronous guard needs the silence.
            let loading;
            Shopware.Feature.silence('V6_8_0_0', () => {
                loading = this.loadAgenticCommerceExportConfig();
            });

            return loading;
        },

        /**
         * @private
         * @deprecated tag:v6.8.0 - Will be removed with `saveAgenticCommerceExportConfig`
         *
         * Calls `saveAgenticCommerceExportConfig` for core with its deprecation silenced,
         * so an override of it keeps working.
         * Reports a successful save with the 6.8 flag, as if the member were removed.
         */
        saveAgenticCommerceExportConfigDeprecated() {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return Promise.resolve(true);
            }

            // `silence()` rejects a returned promise, and only the synchronous guard needs the silence.
            let saving;
            Shopware.Feature.silence('V6_8_0_0', () => {
                saving = this.saveAgenticCommerceExportConfig();
            });

            return saving;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        validateAgenticCommerceExportConfig() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.validateAgenticCommerceExportConfig() is deprecated. Will be removed.',
            );

            const requiredError = new ShopwareError({ code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' });
            const activeProvider =
                this.productExport?.provider ?? this.defaultAgenticCommerceExportConfigDeprecated[0]?.provider;
            let isValid = true;

            const activeEntries = this.agenticCommerceExportConfig.filter((entry) => {
                return entry.isLoaded && entry.provider === activeProvider;
            });

            for (const entry of activeEntries) {
                for (const el of entry.elements.filter((el) => el.config?.required && !entry.values[el.name])) {
                    entry.errors[el.name] = requiredError;
                    isValid = false;
                }
            }

            return isValid;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        async loadAgenticCommerceExportConfig() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.loadAgenticCommerceExportConfig() is deprecated. Will be removed.',
            );

            // Ends the caller's `Feature.silence()` before the body, so it silences only this guard.
            await Promise.resolve();

            this.agenticCommerceExportConfig = this.defaultAgenticCommerceExportConfigDeprecated.map((configEntry) => {
                return {
                    ...configEntry,
                    elements: [],
                    values: {},
                    errors: {},
                    isLoading: false,
                    isLoaded: false,
                };
            });

            if (!this.isAgenticCommerceDeprecated || !this.salesChannel?.id) {
                return;
            }

            await Promise.all(
                this.agenticCommerceExportConfig.map(async (configEntry) => {
                    configEntry.isLoading = true;

                    try {
                        const [config, values] = await Promise.all([
                            this.systemConfigApiService.getConfig(configEntry.systemConfigDomain),
                            this.systemConfigApiService.getValues(configEntry.systemConfigDomain, this.salesChannel.id),
                        ]);

                        configEntry.elements = config.flatMap((card) => card.elements);
                        configEntry.values = values;
                        configEntry.isLoaded = true;
                    } catch (_error) {
                        this.createNotificationError({
                            message: this.$t('sw-sales-channel.detail.messageAPIError'),
                        });
                    } finally {
                        configEntry.isLoading = false;
                    }
                }),
            );
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        async saveAgenticCommerceExportConfig() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-sales-channel-detail.saveAgenticCommerceExportConfig() is deprecated. Will be removed.',
            );

            // Ends the caller's `Feature.silence()` before the body, so it silences only this guard.
            await Promise.resolve();

            if (!this.isAgenticCommerceDeprecated || !this.salesChannel?.id) {
                return true;
            }

            const loadedConfigs = this.agenticCommerceExportConfig.filter((configEntry) => configEntry.isLoaded);

            if (loadedConfigs.length === 0) {
                return true;
            }

            const mergedValues = loadedConfigs.reduce((accumulator, configEntry) => {
                return {
                    ...accumulator,
                    ...objectHelper.deepCopyObject(configEntry.values),
                };
            }, {});

            try {
                await this.systemConfigApiService.batchSave({
                    [this.salesChannel.id]: mergedValues,
                });

                return true;
            } catch (_error) {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.messageSaveError', {
                        name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name'),
                    }),
                });

                return false;
            }
        },

        updateAnalytics() {
            const analyticsId = this.salesChannel.analyticsId;
            if (analyticsId && !this.salesChannel?.analytics?.trackingId) {
                this.salesChannel.analyticsId = null;
                delete this.salesChannel.analytics;
            }

            return analyticsId;
        },

        abortOnLanguageChange() {
            return this.salesChannelRepository.hasChanges(this.salesChannel);
        },

        async saveOnLanguageChange() {
            if (!this.validateRequiredSalesChannelFields()) {
                return Promise.reject();
            }

            const saveSuccessful = await this.saveSalesChannel();

            if (!saveSuccessful) {
                return Promise.reject();
            }

            return true;
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        validateRequiredSalesChannelFields() {
            if (!this.salesChannel) {
                return false;
            }

            const missingFields = this.getRequiredSalesChannelFields().filter((fieldName) => {
                return this.isRequiredFieldEmpty(this.salesChannel[fieldName]);
            });

            if (missingFields.length <= 0) {
                return true;
            }

            this.clearRequiredSalesChannelFieldErrors();
            missingFields.forEach((fieldName) => this.addRequiredSalesChannelFieldError(fieldName));

            this.createNotificationError({
                message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });

            this.isLoading = false;

            return false;
        },

        getRequiredSalesChannelFields() {
            if (this.isProductExportChannel) {
                return REQUIRED_PRODUCT_EXPORT_FIELDS;
            }

            return REQUIRED_BASE_FIELDS;
        },

        isRequiredFieldEmpty(value) {
            if (value === undefined || value === null) {
                return true;
            }

            if (typeof value === 'string') {
                return value.trim() === '';
            }

            return false;
        },

        addRequiredSalesChannelFieldError(fieldName) {
            Shopware.Store.get('error').addApiError({
                expression: this.getRequiredSalesChannelFieldErrorExpression(fieldName),
                error: new ShopwareError(EntityValidationService.createRequiredError(`/0/${fieldName}`)),
            });
        },

        clearRequiredSalesChannelFieldErrors() {
            this.getRequiredSalesChannelFields().forEach((fieldName) => {
                this.clearRequiredSalesChannelFieldError(fieldName);
            });
        },

        clearResolvedRequiredSalesChannelFieldErrors() {
            if (!this.salesChannel?.id) {
                return;
            }

            this.getRequiredSalesChannelFields().forEach((fieldName) => {
                if (this.isRequiredFieldEmpty(this.salesChannel[fieldName])) {
                    return;
                }

                this.clearRequiredSalesChannelFieldError(fieldName);
            });
        },

        clearRequiredSalesChannelFieldError(fieldName) {
            const error = this.getRequiredSalesChannelFieldError(fieldName);

            if (error?.code !== EntityValidationService.ERROR_CODE_REQUIRED) {
                return;
            }

            Shopware.Store.get('error').removeApiError(this.getRequiredSalesChannelFieldErrorExpression(fieldName));
        },

        getRequiredSalesChannelFieldError(fieldName) {
            return Shopware.Store.get('error').getApiErrorFromPath(this.getSalesChannelEntityName(), this.salesChannel.id, [
                fieldName,
            ]);
        },

        getRequiredSalesChannelFieldErrorExpression(fieldName) {
            return `${this.getSalesChannelEntityName()}.${this.salesChannel.id}.${fieldName}`;
        },

        getSalesChannelEntityName() {
            return typeof this.salesChannel.getEntityName === 'function'
                ? this.salesChannel.getEntityName()
                : 'sales_channel';
        },
    },
};
