import template from './sw-sales-channel-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-detail', {

    template,

    inject: [
        'repositoryFactory',
        'context',
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
            storefrontSalesChannels: [],
            isSaveSuccessful: false,
            newProductExport: null,
            productComparisonAccessUrl: null,
            invalidFileName: false,
            templateOptions: [],
            templates: null,
            templateName: null,
            showTemplateModal: false,
            selectedTemplate: null
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

            if (this.newProductExport) {
                return this.newProductExport;
            }

            this.newProductExport = this.productExportRepository.create(this.context);
            this.newProductExport.interval = 0;
            this.newProductExport.generateByCronjob = false;

            return this.newProductExport;
        },

        isStoreFront() {
            return this.salesChannel.typeId === '8a243080f92e4c719546314b577cf82b';
        },

        isProductComparison() {
            if (!this.salesChannel) {
                return this.$route.params.typeId === 'ed535e5722134ac1aa6524f73e26881b';
            }

            return this.salesChannel.typeId === 'ed535e5722134ac1aa6524f73e26881b';
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

            return criteria.addFilter(Criteria.equals('typeId', '8a243080f92e4c719546314b577cf82b'));
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        }
    },

    created() {
        this.createdComponent();
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
            const criteria = this.getLoadSalesChannelCriteria();
            this.isLoading = true;
            this.salesChannelRepository
                .get(this.$route.params.id, this.context, criteria)
                .then((entity) => {
                    this.salesChannel = entity;
                    if (this.isProductComparison) {
                        this.generateAccessUrl();
                    }
                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('paymentMethods');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('countries');
            criteria.addAssociation('currencies');
            criteria.addAssociation('languages');
            criteria.addAssociation('domains');
            criteria.addAssociation('productExports');
            criteria.addAssociation('productExports.salesChannelDomain.salesChannel');

            return criteria;
        },

        onTemplateSelected(templateName) {
            if (this.templates === null || this.templates[templateName] === undefined) {
                return;
            }

            this.selectedTemplate = this.templates[templateName];
            const contentChanged = Object.keys(this.selectedTemplate).some((value) => {
                return this.productExport[value] !== this.selectedTemplate[value];
            });

            if (!contentChanged) {
                return;
            }

            this.showTemplateModal = true;
        },

        onTemplateModalClose() {
            this.selectedTemplate = null;
            this.templateName = null;
            this.showTemplateModal = false;
        },

        onTemplateModalConfirm() {
            Object.keys(this.selectedTemplate).forEach((value) => {
                this.productExport[value] = this.selectedTemplate[value];
            });
            this.onTemplateModalClose();

            this.createNotificationInfo({
                title: this.$tc('sw-sales-channel.detail.productComparison.templates.message.template-applied-title'),
                message: this.$tc('sw-sales-channel.detail.productComparison.templates.message.template-applied-message')
            });
        },

        loadStorefrontSalesChannels() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('typeId', '8a243080f92e4c719546314b577cf82b'));

            this.isLoading = true;
            this.salesChannelRepository
                .search(criteria, this.context)
                .then((searchResult) => {
                    this.storefrontSalesChannels = searchResult;
                    this.isLoading = false;
                });
        },

        loadCustomFieldSets() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'sales_channel'));
            criteria.getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition'));

            this.customFieldRepository
                .search(criteria, this.context)
                .then((searchResult) => {
                    this.customFieldSets = searchResult;
                });
        },

        generateAccessUrl() {
            if (!this.productExport.salesChannelDomain) {
                this.productComparisonAccessUrl = '';
                return;
            }

            const salesChannelDomainUrl = this.productExport.salesChannelDomain.url.replace(/\/+$/g, '');
            this.productComparisonAccessUrl =
                `${salesChannelDomainUrl}/export/${this.productExport.accessKey}/${this.productExport.fileName}`;
        },

        loadProductExportTemplates() {
            this.templateOptions = Object.values(this.exportTemplateService.getProductExportTemplateRegistry());
            this.templates = this.exportTemplateService.getProductExportTemplateRegistry();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        setInvalidFileName(invalidFileName) {
            this.invalidFileName = invalidFileName;
        },

        onSave() {
            this.isLoading = true;

            this.isSaveSuccessful = false;
            if (this.isProductComparison && this.salesChannel.productExports.length === 0) {
                this.salesChannel.productExports.add(this.productExport);
            }

            this.salesChannelRepository
                .save(this.salesChannel, this.context)
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
