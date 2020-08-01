import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.scss';

const { Component, Mixin, Context, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
const domUtils = Shopware.Utils.dom;
const ShopwareError = Shopware.Classes.ShopwareError;
const utils = Shopware.Utils;

const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-sales-channel-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    inject: [
        'salesChannelService',
        'productExportService',
        'repositoryFactory',
        'knownIpsService'
    ],

    props: {
        salesChannel: {
            required: true
        },

        productExport: {
            // type: Entity
            type: Object,
            required: true
        },

        storefrontSalesChannelCriteria: {
            type: Criteria,
            required: false
        },

        customFieldSets: {
            type: Array,
            required: true
        },

        isLoading: {
            type: Boolean,
            default: false
        },

        productComparisonAccessUrl: {
            type: String,
            default: ''
        },

        templateOptions: {
            type: Array,
            default: []
        },

        showTemplateModal: {
            type: Boolean,
            default: false
        },

        templateName: {
            type: String,
            default: null
        }
    },

    watch: {
        'productExport.fileName'() {
            this.onChangeFileName();
        }
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
            knownIps: []
        };
    },

    created() {
        this.knownIpsService.getKnownIps().then(ips => {
            this.knownIps = ips;
        });
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
            const criteria = new Criteria();

            return criteria.addFilter(Criteria.equals('salesChannelId', this.productExport.storefrontSalesChannelId));
        },

        storefrontSalesChannelCurrencyCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('salesChannels');

            return criteria.addFilter(Criteria.equals('salesChannels.id', this.productExport.storefrontSalesChannelId));
        },

        storefrontDomainsLoaded() {
            return this.storefrontDomains.length > 0;
        },

        domainRepository() {
            return this.repositoryFactory.create(
                this.salesChannel.domains.entity,
                this.salesChannel.domains.source
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
            return criteria.addFilter(Criteria.equals('type', 'page'));
        },

        getIntervalOptions() {
            return [
                {
                    id: 0,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.0')
                },
                {
                    id: 120,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.120')
                },
                {
                    id: 300,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.300')
                },
                {
                    id: 600,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.600')
                },
                {
                    id: 900,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.900')
                },
                {
                    id: 1800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.1800')
                },
                {
                    id: 3600,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.3600')
                },
                {
                    id: 7200,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.7200')
                },
                {
                    id: 14400,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.14400')
                },
                {
                    id: 28800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.28800')
                },
                {
                    id: 43200,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.43200')
                },
                {
                    id: 86400,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.86400')
                },
                {
                    id: 172800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.172800')
                },
                {
                    id: 259200,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.259200')
                },
                {
                    id: 345600,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.345600')
                },
                {
                    id: 432000,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.432000')
                },
                {
                    id: 518400,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.518400')
                },
                {
                    id: 604800,
                    name: this.$tc('sw-sales-channel.detail.productComparison.intervalLabels.604800')
                }
            ];
        },

        getFileFormatOptions() {
            return [
                {
                    id: 'csv',
                    name: this.$tc('sw-sales-channel.detail.productComparison.fileFormatLabels.csv')
                },
                {
                    id: 'xml',
                    name: this.$tc('sw-sales-channel.detail.productComparison.fileFormatLabels.xml')
                }
            ];
        },

        getEncodingOptions() {
            return [
                {
                    id: 'ISO-8859-1',
                    name: 'ISO-8859-1'
                },
                {
                    id: 'UTF-8',
                    name: 'UTF-8'
                }
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

        taxCalculationTypeOptions() {
            return [
                {
                    value: 'horizontal',
                    name: this.$tc('sw-sales-channel.detail.taxCalculation.horizontalName'),
                    description: this.$tc('sw-sales-channel.detail.taxCalculation.horizontalDescription')
                },
                {
                    value: 'vertical',
                    name: this.$tc('sw-sales-channel.detail.taxCalculation.verticalName'),
                    description: this.$tc('sw-sales-channel.detail.taxCalculation.verticalDescription')
                }
            ];
        },

        maintenanceIpWhitelist: {
            get() {
                return this.salesChannel.maintenanceIpWhitelist ? this.salesChannel.maintenanceIpWhitelist : [];
            },
            set(value) {
                this.salesChannel.maintenanceIpWhitelist = value;
            }
        },

        ...mapPropertyErrors('salesChannel',
            [
                'name',
                'customerGroupId',
                'navigationCategoryId'
            ]),

        ...mapPropertyErrors('productExport',
            [
                'productStreamId',
                'encoding',
                'fileName',
                'fileFormat',
                'salesChannelDomainId',
                'currencyId'
            ])
    },

    methods: {
        onGenerateKeys() {
            this.salesChannelService.generateKey().then((response) => {
                this.salesChannel.accessKey = response.accessKey;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-sales-channel.detail.messageAPIError')
                });
            });
        },

        onGenerateProductExportKey(displaySaveNotification = true) {
            this.productExportService.generateKey().then((response) => {
                this.productExport.accessKey = response.accessKey;
                this.$emit('access-key-changed');

                if (displaySaveNotification) {
                    this.createNotificationInfo({
                        title: this.$tc('global.default.info'),
                        message: this.$tc('sw-sales-channel.detail.productComparison.messageAccessKeyChanged')
                    });
                }
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-sales-channel.detail.messageAPIError')
                });
            });
        },

        onToggleActive() {
            if (this.salesChannel.active !== true || this.isProductComparison) {
                return;
            }

            const criteria = new Criteria();
            criteria.addAssociation('themes');

            this.salesChannelRepository
                .get(this.$route.params.id, Context.api, criteria)
                .then((entity) => {
                    if (entity.extensions.themes !== undefined && entity.extensions.themes.length >= 1) {
                        return;
                    }

                    this.salesChannel.active = false;
                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: this.$tc('sw-sales-channel.detail.messageActivateWithoutThemeError', 0, {
                            name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name')
                        })
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
            });
        },

        copyToClipboard() {
            domUtils.copyToClipboard(this.salesChannel.accessKey);
        },

        onStorefrontSelectionChange(storefrontSalesChannelId) {
            this.salesChannelRepository
                .get(storefrontSalesChannelId, Shopware.Context.api)
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
            this.globalDomainRepository
                .get(storefrontSalesChannelDomainId, Shopware.Context.api)
                .then((entity) => {
                    this.productExport.salesChannelDomain = entity;
                    this.productExport.currencyId = entity.currencyId;
                    this.$emit('domain-changed');
                });
        },

        loadStorefrontDomains(storefrontSalesChannelId) {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('salesChannelId', storefrontSalesChannelId));

            this.globalDomainRepository
                .search(criteria, Shopware.Context.api)
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
                        Criteria.not('AND', [Criteria.equals('id', this.productExport.id)])
                    ]
                )
            );

            this.productExportRepository.search(criteria, Shopware.Context.api).then(({ total }) => {
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
        }
    }
});
