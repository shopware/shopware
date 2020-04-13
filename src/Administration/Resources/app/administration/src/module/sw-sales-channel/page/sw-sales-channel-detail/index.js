import template from './sw-sales-channel-detail.html.twig';
import swSalesChannelState from '../../state/salesChannel.store';

const { Component, Mixin, Context, Defaults, Utils, State } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-detail', {
    template,

    inject: [
        'repositoryFactory',
        'exportTemplateService'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave'
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
                selectedTemplate: null
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
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

            this.productComparison.newProductExport = this.productExportRepository.create(Shopware.Context.api);
            this.productComparison.newProductExport.interval = 0;
            this.productComparison.newProductExport.generateByCronjob = false;

            return this.productComparison.newProductExport;
        },

        googleShopping() {
            if (this.salesChannel && this.salesChannel.productExports.first()) {
                return this.salesChannel.productExports.first();
            }

            this.productComparison.newProductExport.encoding = 'UTF-8';
            this.productComparison.newProductExport.fileFormat = 'xml';
            this.productComparison.newProductExport.fileName = Utils.createId();
            this.productComparison.newProductExport.interval = 0;
            this.productComparison.newProductExport.generateByCronjob = false;
            this.productComparison.newProductExport.accessKey = this.salesChannel.accessKey;

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

        isGoogleShopping() {
            if (!this.salesChannel) {
                return this.$route.params.typeId === Defaults.googleShoppingTypeId;
            }

            return this.salesChannel.typeId === Defaults.googleShoppingTypeId;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        productExportRepository() {
            return this.repositoryFactory.create('product_export');
        },

        storefrontSalesChannelCriteria() {
            const criteria = new Criteria();

            return criteria.addFilter(Criteria.equals('typeId', Defaults.storefrontSalesChannelTypeId));
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        }
    },

    beforeCreate() {
        State.registerModule('swSalesChannel', swSalesChannelState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.loadEntityData();
            this.loadProductExportTemplates();
        },

        beforeDestroyComponent() {
            State.unregisterModule('swSalesChannel');
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
                .get(this.$route.params.id, Shopware.Context.api, this.getLoadSalesChannelCriteria())
                .then((entity) => {
                    this.salesChannel = entity;

                    if (!this.salesChannel.maintenanceIpWhitelist) {
                        this.salesChannel.maintenanceIpWhitelist = [];
                    }

                    this.generateAccessUrl();

                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('paymentMethods');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('countries');
            criteria.addAssociation('currencies');
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
                title: this.$tc('sw-sales-channel.detail.productComparison.templates.message.template-applied-title'),
                message: this.$tc('sw-sales-channel.detail.productComparison.templates.message.template-applied-message')
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

            const salesChannelDomainUrl = this.productExport.salesChannelDomain.url.replace(/\/+$/g, '');
            this.productComparison.productComparisonAccessUrl =
                `${salesChannelDomainUrl}/export/${this.productExport.accessKey}/${this.productExport.fileName}`;
        },

        loadProductExportTemplates() {
            this.productComparison.templateOptions = Object.values(
                this.exportTemplateService.getProductExportTemplateRegistry()
            );
            this.productComparison.templates = this.exportTemplateService.getProductExportTemplateRegistry();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        setInvalidFileName(invalidFileName) {
            this.productComparison.invalidFileName = invalidFileName;
        },

        onSave() {
            this.isLoading = true;

            this.isSaveSuccessful = false;
            if (this.isProductComparison && !this.salesChannel.productExports.length) {
                this.salesChannel.productExports.add(this.productExport);
            }

            if (this.isGoogleShopping && !this.salesChannel.productExports.length) {
                this.salesChannel.productExports.add(this.googleShopping);
            }


            this.salesChannelRepository
                .save(this.salesChannel, Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;

                    this.$root.$emit('sales-channel-change');
                    this.loadEntityData();
                }).catch(() => {
                    this.isLoading = false;

                    this.createNotificationError({
                        title: this.$tc('sw-sales-channel.detail.titleSaveError'),
                        message: this.$tc('sw-sales-channel.detail.messageSaveError', 0, {
                            name: this.salesChannel.name || this.placeholder(this.salesChannel, 'name')
                        })
                    });
                });
        },

        abortOnLanguageChange() {
            return this.salesChannelRepository.hasChanges(this.salesChannel);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
