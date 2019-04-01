import { Component, State, Mixin } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-settings-document-detail.html.twig';
import './sw-settings-document-detail.scss';

Component.register('sw-settings-document-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            documentConfig: {},
            documentConfigSalesChannelsStore: {},
            documentConfigSalesChannels: [],
            documentConfigSalesChannelsAssoc: {},
            salesChannels: {},
            selectedType: {},
            attributeSet: {
                id: 'documentconfiguration',
                name: 'Document Konfiguration',
                config: {
                    label: {
                        'de-DE': 'Dokumentenkonfiguration',
                        'en-GB': 'Document configuration'
                    }
                },
                attributes: [
                    {
                        id: 'docconfigdisplayprice',
                        name: 'displayPrices',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayPrices')
                        }
                    },
                    {
                        id: 'docconfigdisplayfooter',
                        name: 'displayFooter',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayFooter')
                        }
                    },
                    {
                        id: 'docconfigdisplayheader',
                        name: 'displayHeader',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayHeader')
                        }
                    },
                    {
                        id: 'docconfigdisplaylineitems',
                        name: 'displayLineItems',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayLineItems')
                        }
                    },
                    {
                        id: 'docconfigdisplaylineitemposition',
                        name: 'displayLineItemPosition',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayLineItemPosition')
                        }
                    },
                    {
                        id: 'docconfigdisplaypagecount',
                        name: 'displayPageCount',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayPageCount')
                        }
                    },
                    {
                        id: 'docconfigpageorientiation',
                        name: 'pageOrientation',
                        type: 'radio',
                        config: {
                            componentName: 'sw-select',
                            options: [
                                { id: 'portrait', name: 'Portrait' },
                                { id: 'landscape', name: 'Landscape' }
                            ],
                            label: this.$tc('sw-settings-document.detail.labelPageOrientation')
                        }
                    },
                    {
                        id: 'docconfigitemsperpage',
                        name: 'itemsPerPage',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelItemsPerPage')
                        }
                    },
                    {
                        id: 'docconfigdisplaycompanyaddress',
                        name: 'displayCompanyAddress',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: this.$tc('sw-settings-document.detail.labelDisplayCompanyAddress')
                        }
                    },
                    {
                        id: 'docconfigcompanyaddress',
                        name: 'companyAddress',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelCompanyAddress')
                        }
                    },
                    {
                        id: 'docconfigcompanyname',
                        name: 'companyName',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelCompanyName')
                        }
                    },
                    {
                        id: 'docconfigcompanyemail',
                        name: 'companyEmail',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelCompanyEmail')
                        }
                    },
                    {
                        id: 'docconfigcompanyurl',
                        name: 'companyUrl',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelCompanyUrl')
                        }
                    },
                    {
                        id: 'docconfigtaxnumber',
                        name: 'taxNumber',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelTaxNumber')
                        }
                    },
                    {
                        id: 'docconfigvatid',
                        name: 'vatId',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelVatId')
                        }
                    },
                    {
                        id: 'docconfigbankname',
                        name: 'bankName',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelBankName')
                        }
                    },
                    {
                        id: 'docconfigiban',
                        name: 'bankIban',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelBankIban')
                        }
                    },
                    {
                        id: 'docconfigbic',
                        name: 'bankBic',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelBankBic')
                        }
                    },
                    {
                        id: 'docconfigcompanyplaceofjurisdiction',
                        name: 'placeOfJurisdiction',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelPlaceOfJurisdiction')
                        }
                    },
                    {
                        id: 'docconfigplaceoffulillment',
                        name: 'placeOfFulfillment',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelPlaceOfFulfillment')
                        }
                    },
                    {
                        id: 'docconfigexecutivedirector',
                        name: 'executiveDirector',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: this.$tc('sw-settings-document.detail.labelExecutiveDirector')
                        }
                    }
                ]
            }
        };
    },

    computed: {
        documentBaseConfigStore() {
            return State.getStore('document_base_config');
        },
        documentTypeStore() {
            return State.getStore('document_type');
        },
        salesChannelStore() {
            return State.getStore('sales_channel');
        },
        documentBaseConfigSalesChannelAssociationStore() {
            return this.documentConfig.getAssociation('salesChannels');
        },
        salesChannelAssociationStore() {
            return this.salesChannels.getAssociation('documentBaseConfig');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.$route.params.id && this.documentConfig.isLoading !== true) {
                this.documentConfigId = this.$route.params.id;
                this.loadEntityData();
            }
            this.documentConfigSalesChannelsStore = new LocalStore();
            this.isLoading = false;
        },

        loadEntityData() {
            this.salesChannelStore.getList().then((response) => {
                this.salesChannels = response;
            });
            this.documentBaseConfigStore.getByIdAsync(this.documentConfigId).then((response) => {
                this.documentConfig = response;
                if (this.documentConfig.config === null) {
                    this.documentConfig.config = [];
                }
                this.selectedType = this.documentTypeStore.getById(this.documentConfig.typeId);
                if (this.documentConfig.global === false) {
                    this.documentBaseConfigSalesChannelAssociationStore.getList({
                        associations: { salesChannel: {} }
                    }).then((responseAssoc) => {
                        this.documentConfigSalesChannelsAssoc = responseAssoc;
                        this.documentConfigSalesChannelsAssoc.items.forEach((salesChannelAssoc) => {
                            if (salesChannelAssoc.salesChannelId !== null) {
                                this.documentConfigSalesChannelsStore.add(salesChannelAssoc.salesChannel);
                            }
                        });
                        this.$refs.documentSalesChannel.loadSelected(true);
                        this.$refs.documentSalesChannel.updateValue();
                    });
                }
            });
        },

        showOption(item) {
            return item.id !== this.documentConfig.id;
        },

        onChangeType(id) {
            this.selectedType = this.documentTypeStore.getById(id);
            this.documentBaseConfigSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                salesChannelAssoc.documentTypeId = this.selectedType.id;
            });
        },
        onChangeSalesChannel() {
            this.$refs.documentSalesChannel.updateValue();
            if (Object.keys(this.documentConfig).length === 0) {
                return;
            }
            // check selected saleschannels and associate to config
            if (this.documentConfigSalesChannels && this.documentConfigSalesChannels.length > 0) {
                this.documentConfigSalesChannels.forEach((salesChannel) => {
                    if (!this.configHasSaleschannel(salesChannel)) {
                        const assocConfig = this.documentBaseConfigSalesChannelAssociationStore.create();
                        assocConfig.documentBaseConfigId = this.documentConfig.id;
                        assocConfig.documentTypeId = this.selectedType.id;
                        assocConfig.salesChannelId = salesChannel;
                    } else {
                        this.undeleteSaleschannel(salesChannel);
                    }
                });
            }
            this.documentBaseConfigSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (!this.selectHasSaleschannel(salesChannelAssoc.salesChannelId)) {
                    salesChannelAssoc.delete();
                }
            });
        },

        configHasSaleschannel(salesChannelId) {
            let found = false;
            this.documentBaseConfigSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId) {
                    found = true;
                }
            });
            return found;
        },

        selectHasSaleschannel(salesChannelId) {
            return (this.documentConfigSalesChannels && this.documentConfigSalesChannels.indexOf(salesChannelId) !== -1);
        },

        undeleteSaleschannel(salesChannelId) {
            this.documentBaseConfigSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId && salesChannelAssoc.isDeleted === true) {
                    salesChannelAssoc.isDeleted = false;
                }
            });
        },

        onSave() {
            this.onChangeSalesChannel();
            const documentConfigName = this.documentConfig.name;
            const titleSaveSuccess = this.$tc('sw-settings-document.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-document.detail.messageSaveSuccess',
                0,
                { name: documentConfigName }
            );
            return this.documentConfig.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
