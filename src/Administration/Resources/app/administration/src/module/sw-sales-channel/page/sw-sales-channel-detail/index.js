/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail.html.twig';

const { Mixin, Context, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
const objectHelper = Shopware.Utils.object;
const ShopwareError = Shopware.Classes.ShopwareError;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'exportTemplateService',
        'systemConfigApiService',
        'acl',
        'feature',
    ],

    provide() {
        return {
            swSalesChannelDetailGetAgenticCommerceExportConfig: () => this.agenticCommerceExportConfig,
        };
    },

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

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

        isAgenticCommerce() {
            if (!this.salesChannel) {
                return this.$route.params.typeId === Defaults.agenticCommerceTypeId;
            }

            return this.salesChannel.typeId === Defaults.agenticCommerceTypeId;
        },

        isProductExportChannel() {
            return this.isProductComparison || this.isAgenticCommerce;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelAnalyticsRepository() {
            return this.repositoryFactory.create('sales_channel_analytics');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        productExportRepository() {
            return this.repositoryFactory.create('product_export');
        },

        storefrontSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            return criteria.addFilter(Criteria.equals('typeId', Defaults.storefrontSalesChannelTypeId));
        },

        tooltipSave() {
            if (!this.allowSaving) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
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

        defaultAgenticCommerceExportConfig() {
            return [
                {
                    provider: 'open-ai',
                    systemConfigDomain: 'core.openAiProductExport',
                    titleSnippet: 'sw-sales-channel.detail.agenticCommerce.openAiSettingsTitle',
                    positionIdentifier: 'sw-sales-channel-detail-base-agentic-commerce-export-config-open-ai',
                },
            ];
        },
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
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
                this.loadAgenticCommerceExportConfig();
                return;
            }

            if (!hasRouteId) {
                return;
            }

            if (hasRouteTypeId) {
                this.loadAgenticCommerceExportConfig();
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

                    // eslint-disable-next-line inclusive-language/use-inclusive-words
                    if (!this.salesChannel.maintenanceIpWhitelist) {
                        // eslint-disable-next-line inclusive-language/use-inclusive-words
                        this.salesChannel.maintenanceIpWhitelist = [];
                    }

                    this.generateAccessUrl();
                    this.loadAgenticCommerceExportConfig();
                    this.detectCurrentTemplate();

                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('paymentMethods');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('countries');
            criteria.getAssociation('currencies').addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('domains');
            criteria
                .getAssociation('languages')
                .addSorting(Criteria.sort('name', 'ASC'))
                .addFilter(Criteria.equals('active', true));
            criteria.addAssociation('analytics');

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
                message: this.$tc('sw-sales-channel.detail.productComparison.templates.message.template-applied-message'),
            });
        },

        loadCustomFieldSets() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'sales_channel'));
            criteria.getAssociation('customFields').addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            this.customFieldRepository.search(criteria, Context.api).then((searchResult) => {
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
                    message: this.$tc(
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
            if (!this.validateAgenticCommerceExportConfig()) {
                this.isLoading = false;
                return;
            }

            const saveSuccessful = await this.saveSalesChannel();

            if (!saveSuccessful) {
                return;
            }

            const configSaveSuccessful = await this.saveAgenticCommerceExportConfig();

            if (!configSaveSuccessful) {
                return;
            }

            this.loadEntityData();
        },

        validateAgenticCommerceExportConfig() {
            const requiredError = new ShopwareError({ code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' });
            let isValid = true;

            for (const entry of this.agenticCommerceExportConfig.filter((e) => e.isLoaded)) {
                for (const el of entry.elements.filter((el) => el.config?.required && !entry.values[el.name])) {
                    entry.errors[el.name] = requiredError;
                    isValid = false;
                }
            }

            return isValid;
        },

        async loadAgenticCommerceExportConfig() {
            this.agenticCommerceExportConfig = this.defaultAgenticCommerceExportConfig.map((configEntry) => {
                return {
                    ...configEntry,
                    elements: [],
                    values: {},
                    errors: {},
                    isLoading: false,
                    isLoaded: false,
                };
            });

            if (!this.isAgenticCommerce || !this.salesChannel?.id) {
                return;
            }

            await Promise.all(
                this.agenticCommerceExportConfig.map(async (configEntry) => {
                    configEntry.isLoading = true;

                    try {
                        const [
                            config,
                            values,
                        ] = await Promise.all([
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

        async saveAgenticCommerceExportConfig() {
            if (!this.isAgenticCommerce || !this.salesChannel?.id) {
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
            await this.saveSalesChannel();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },
    },
};
