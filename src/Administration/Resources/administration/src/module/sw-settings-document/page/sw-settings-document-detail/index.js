import { Component, State, Mixin } from 'src/core/shopware';
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
        salesChannelAssociationStore() {
            return this.documentConfig.getAssociation('salesChannels');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.documentConfigId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.documentBaseConfigStore.getByIdAsync(this.documentConfigId).then((response) => {
                this.documentConfig = response;
            });
        },

        showOption(item) {
            return item.id !== this.documentConfig.id;
        },

        onChangeType(id) {
            this.selectedType = this.numberRangeTypeStore.getById(id);
        },

        onSave() {
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
