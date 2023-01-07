/**
 * @package sales-channel
 */

import template from './sw-sales-channel-detail.html.twig';

const { Mixin, Context, Defaults } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'exportTemplateService',
        'acl',
        'feature',
    ],

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
                showTemplateModal: false,
                selectedTemplate: null,
            },
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

            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.productComparison.newProductExport = this.productExportRepository.create();
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.productComparison.newProductExport.interval = 0;
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.productComparison.newProductExport.generateByCronjob = false;

            return this.productComparison.newProductExport;
        },

        isStoreFront() {
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
            if (!this.$route.params.id) {
                return;
            }

            if (this.$route.params.typeId) {
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
                .get(this.$route.params.id, Context.api, this.getLoadSalesChannelCriteria())
                .then((entity) => {
                    this.salesChannel = entity;

                    // eslint-disable-next-line inclusive-language/use-inclusive-words
                    if (!this.salesChannel.maintenanceIpWhitelist) {
                        // eslint-disable-next-line inclusive-language/use-inclusive-words
                        this.salesChannel.maintenanceIpWhitelist = [];
                    }

                    this.generateAccessUrl();

                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('paymentMethods');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('countries');
            criteria.getAssociation('currencies')
                .addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('domains');
            criteria.addAssociation('languages');
            criteria.addAssociation('analytics');

            criteria.addAssociation('productExports');
            criteria.addAssociation('productExports.salesChannelDomain.salesChannel');

            criteria.addAssociation('domains.language');
            criteria.addAssociation('domains.snippetSet');
            criteria.addAssociation('domains.currency');

            return criteria;
        },

        onTemplateSelected(templateName) {
            if (this.productComparison.templates === null || this.productComparison.templates[templateName] === undefined) {
                return;
            }

            this.productComparison.selectedTemplate = this.productComparison.templates[templateName];
            const contentChanged = Object.keys(this.productComparison.selectedTemplate).some((value) => {
                return this.productExport[value] !== this.productComparison.selectedTemplate[value];
            });

            if (!contentChanged) {
                return;
            }

            this.productComparison.showTemplateModal = true;
        },

        onTemplateModalClose() {
            this.productComparison.selectedTemplate = null;
            this.productComparison.templateName = null;
            this.productComparison.showTemplateModal = false;
        },

        onTemplateModalConfirm() {
            Object.keys(this.productComparison.selectedTemplate).forEach((value) => {
                this.productExport[value] = this.productComparison.selectedTemplate[value];
            });
            this.onTemplateModalClose();

            this.createNotificationInfo({
                message: this.$tc('sw-sales-channel.detail.productComparison.templates.message.template-applied-message'),
            });
        },

        loadCustomFieldSets() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'sales_channel'));
            criteria.getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            this.customFieldRepository
                .search(criteria, Context.api)
                .then((searchResult) => {
                    this.customFieldSets = searchResult;
                });
        },

        generateAccessUrl() {
            if (!this.productExport.salesChannelDomain) {
                this.productComparison.productComparisonAccessUrl = '';
                return;
            }

            const domainUrl = this.productExport.salesChannelDomain.url.replace(/\/+$/g, '');
            this.productComparison.productComparisonAccessUrl =
                `${domainUrl}/store-api/product-export/${this.productExport.accessKey}/${this.productExport.fileName}`;
        },

        loadProductExportTemplates() {
            this.productComparison.templateOptions = Object.values(
                this.exportTemplateService.getProductExportTemplateRegistry(),
            );
            this.productComparison.templates = this.exportTemplateService.getProductExportTemplateRegistry();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        setInvalidFileName(invalidFileName) {
            this.productComparison.invalidFileName = invalidFileName;
        },

        async onSave() {
            this.isLoading = true;

            this.isSaveSuccessful = false;
            if (this.isProductComparison && !this.salesChannel.productExports.length) {
                this.salesChannel.productExports.add(this.productExport);
            }

            const analyticsId = this.updateAnalytics();

            try {
                await this.salesChannelRepository.save(this.salesChannel, Context.api);

                if (analyticsId && !this.salesChannel?.analytics?.trackingId) {
                    await this.salesChannelAnalyticsRepository.delete(analyticsId, Context.api);
                }

                this.isLoading = false;
                this.isSaveSuccessful = true;

                this.$root.$emit('sales-channel-change');
                this.loadEntityData();
            } catch (error) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$tc('sw-sales-channel.detail.messageSaveError', 0, {
                        name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name'),
                    }),
                });
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

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },
    },
};
