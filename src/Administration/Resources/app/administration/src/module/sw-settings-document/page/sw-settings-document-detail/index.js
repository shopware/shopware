import template from './sw-settings-document-detail.html.twig';
import './sw-settings-document-detail.scss';

const { Component, Data, Mixin } = Shopware;
const { Criteria, EntityCollection } = Data;

Component.register('sw-settings-document-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        documentConfigId: {
            type: String,
            required: false,
            default: null
        }
    },


    data() {
        return {
            documentConfig: {},
            documentConfigSalesChannelOptionsCollection: [],
            documentConfigSalesChannels: [],
            isLoading: false,
            isSaveSuccessful: false,
            salesChannels: {},
            selectedType: {},
            generalFormFields: [
                {
                    name: 'pageOrientation',
                    type: 'radio',
                    config: {
                        componentName: 'sw-single-select',
                        labelProperty: 'name',
                        valueProperty: 'id',
                        options: [
                            { id: 'portrait', name: 'Portrait' },
                            { id: 'landscape', name: 'Landscape' }
                        ],
                        label: this.$tc('sw-settings-document.detail.labelPageOrientation')
                    }
                },
                {
                    name: 'pageSize',
                    type: 'radio',
                    config: {
                        componentName: 'sw-single-select',
                        labelProperty: 'name',
                        valueProperty: 'id',
                        options: [
                            { id: 'a4', name: 'A4' },
                            { id: 'a5', name: 'A5' },
                            { id: 'legal', name: 'Legal' },
                            { id: 'letter', name: 'Letter' }
                        ],
                        label: this.$tc('sw-settings-document.detail.labelPageSize')
                    }
                },
                {
                    name: 'displayHeader',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayHeader')
                    }
                },
                {
                    name: 'displayFooter',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayFooter')
                    }
                },
                {
                    name: 'displayPageCount',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayPageCount')
                    }
                },
                {
                    name: 'displayLineItems',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayLineItems')
                    }
                },
                {
                    name: 'displayLineItemPosition',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayLineItemPosition')
                    }
                },
                {
                    name: 'displayPrices',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayPrices')
                    }
                },


                {
                    name: 'itemsPerPage',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelItemsPerPage')
                    }
                }
            ],
            companyFormFields: [
                {
                    name: 'displayCompanyAddress',
                    type: 'bool',
                    config: {
                        type: 'checkbox',
                        label: this.$tc('sw-settings-document.detail.labelDisplayCompanyAddress'),
                        class: 'sw-settings-document-detail__company-address-checkbox'
                    }
                },
                {
                    name: 'companyAddress',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelCompanyAddress')
                    }
                },
                {
                    name: 'companyName',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelCompanyName')
                    }
                },
                {
                    name: 'companyEmail',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelCompanyEmail')
                    }
                },
                {
                    name: 'companyUrl',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelCompanyUrl')
                    }
                },
                {
                    name: 'taxNumber',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelTaxNumber')
                    }
                },
                {
                    name: 'taxOffice',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelTaxOffice')
                    }
                },
                {
                    name: 'vatId',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelVatId')
                    }
                },
                {
                    name: 'bankName',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelBankName')
                    }
                },
                {
                    name: 'bankIban',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelBankIban')
                    }
                },
                {
                    name: 'bankBic',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelBankBic')
                    }
                },
                {
                    name: 'placeOfJurisdiction',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelPlaceOfJurisdiction')
                    }
                },
                {
                    name: 'placeOfFulfillment',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelPlaceOfFulfillment')
                    }
                },
                {
                    name: 'executiveDirector',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: this.$tc('sw-settings-document.detail.labelExecutiveDirector')
                    }
                }
            ],
            alreadyAssignedSalesChannelIdsToType: [],
            typeIsLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.documentConfig ? this.documentConfig.name : '';
        },

        documentBaseConfigCriteria() {
            const criteria = new Criteria();

            criteria
                .addAssociation('documentType')
                .getAssociation('salesChannels')
                .addAssociation('salesChannel');

            return criteria;
        },

        documentBaseConfigRepository() {
            return this.repositoryFactory.create('document_base_config');
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        documentBaseConfigSalesChannelRepository() {
            return this.repositoryFactory.create('document_base_config_sales_channel');
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            await this.loadAvailableSalesChannel();
            if (this.documentConfigId) {
                await this.loadEntityData();
            } else {
                this.documentConfig = this.documentBaseConfigRepository.create(Shopware.Context.api);
                this.documentConfig.global = false;
                this.documentConfig.config = {};
            }

            this.isLoading = false;
        },

        async loadEntityData() {
            this.documentConfig = await this.documentBaseConfigRepository.get(
                this.documentConfigId,
                Shopware.Context.api,
                this.documentBaseConfigCriteria
            );

            if (this.documentConfig.config === null) {
                this.documentConfig.config = {};
            }
            await this.onChangeType(this.documentConfig.documentType);

            this.documentConfig.salesChannels.forEach(salesChannelAssoc => {
                this.documentConfigSalesChannels.push(salesChannelAssoc.id);
            });
        },

        async loadAvailableSalesChannel() {
            this.salesChannels = await this.salesChannelRepository.iterateAsync();
        },

        showOption(item) {
            return item.id !== this.documentConfig.id;
        },

        async onChangeType(documentType) {
            if (!documentType) {
                return;
            }

            this.typeIsLoading = true;

            this.documentConfig.documentType = documentType;
            this.documentConfigSalesChannels = [];

            this.createSalesChannelSelectOptions();
            const documentSalesChannelCriteria = new Criteria();
            documentSalesChannelCriteria.addFilter(
                Criteria.equals('documentTypeId', documentType.id)
            );

            this.documentBaseConfigSalesChannelRepository.search(documentSalesChannelCriteria, Shopware.Context.api)
                .then((responseSalesChannels) => {
                    this.alreadyAssignedSalesChannelIdsToType = [];
                    responseSalesChannels.forEach((salesChannel) => {
                        if (salesChannel.salesChannelId !== null
                            && salesChannel.documentBaseConfigId !== this.documentConfig.id) {
                            this.alreadyAssignedSalesChannelIdsToType.push(salesChannel.salesChannelId);
                        }
                    });
                    this.typeIsLoading = false;
                });
        },

        onChangeSalesChannel() {
            // check selected sales channels and associate to config
            if (this.documentConfigSalesChannels && this.documentConfigSalesChannels.length > 0) {
                this.documentConfigSalesChannels.forEach((salesChannelId) => {
                    if (!this.documentConfig.salesChannels.has(salesChannelId)) {
                        this.documentConfig.salesChannels.push(
                            this.documentConfigSalesChannelOptionsCollection.get(salesChannelId)
                        );
                    }
                });
            }

            this.documentConfig.salesChannels.forEach((salesChannelAssoc) => {
                if (!this.documentConfigSalesChannels.includes(salesChannelAssoc.id)) {
                    this.documentConfig.salesChannels.remove(salesChannelAssoc.id);
                }
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.onChangeSalesChannel();

            this.documentBaseConfigRepository.save(this.documentConfig, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            }).then(() => {
                this.loadEntityData();
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.document.index' });
        },

        createSalesChannelSelectOptions() {
            this.documentConfigSalesChannelOptionsCollection = new EntityCollection(
                this.documentConfig.salesChannels.source,
                this.documentConfig.salesChannels.entity,
                Shopware.Context.api
            );

            // Abort if no type is assigned yet
            if (!this.documentConfig.documentType) {
                return;
            }

            this.salesChannels.forEach(salesChannel => {
                let salesChannelAlreadyAssigned = false;
                this.documentConfig.salesChannels.forEach(documentConfigSalesChannel => {
                    if (documentConfigSalesChannel.salesChannelId === salesChannel.id) {
                        salesChannelAlreadyAssigned = true;
                        this.documentConfigSalesChannelOptionsCollection.push(documentConfigSalesChannel);
                    }
                });
                if (!salesChannelAlreadyAssigned) {
                    const option = this.documentBaseConfigSalesChannelRepository.create();
                    option.documentBaseConfigId = this.documentConfig.id;
                    option.documentTypeId = this.documentConfig.documentType.id;
                    option.salesChannelId = salesChannel.id;
                    option.salesChannel = salesChannel;
                    this.documentConfigSalesChannelOptionsCollection.push(option);
                }
            });
        }
    }
});
