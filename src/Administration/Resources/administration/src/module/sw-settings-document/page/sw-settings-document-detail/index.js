import { Component, State, Mixin } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-settings-document-detail.html.twig';
import './sw-settings-document-detail.scss';

Component.register('sw-settings-document-detail', {
    template,

    inject: ['repositoryFactory', 'context'],
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
            salesChannelsTypeCriteria: {},
            selectedType: {},
            formFieldSet: {
                id: 'documentconfiguration',
                name: 'Document Konfiguration',
                config: {
                    label: {
                        'de-DE': 'Dokumente',
                        'en-GB': 'Documents'
                    }
                },
                formFields: [
                    {
                        id: 'docconfigdisplayprice',
                        name: 'displayPrices',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayPrices', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayPrices', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigdisplayfooter',
                        name: 'displayFooter',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayFooter', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayFooter', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigdisplayheader',
                        name: 'displayHeader',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayHeader', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayHeader', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigdisplaylineitems',
                        name: 'displayLineItems',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayLineItems', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayLineItems', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigdisplaylineitemposition',
                        name: 'displayLineItemPosition',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayLineItemPosition', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayLineItemPosition', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigdisplaypagecount',
                        name: 'displayPageCount',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayPageCount', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayPageCount', 'de-DE')
                            }
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
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelPageOrientation', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelPageOrientation', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigpagesize',
                        name: 'pageSize',
                        type: 'radio',
                        config: {
                            componentName: 'sw-select',
                            options: [
                                { id: 'a4', name: 'A4' },
                                { id: 'a5', name: 'A5' },
                                { id: 'legal', name: 'Legal' },
                                { id: 'letter', name: 'Letter' }
                            ],
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelPageSize', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelPageSize', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigitemsperpage',
                        name: 'itemsPerPage',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelItemsPerPage', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelItemsPerPage', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigdisplaycompanyaddress',
                        name: 'displayCompanyAddress',
                        type: 'bool',
                        config: {
                            type: 'checkbox',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelDisplayCompanyAddress', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelDisplayCompanyAddress', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigcompanyaddress',
                        name: 'companyAddress',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelCompanyAddress', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelCompanyAddress', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigcompanyname',
                        name: 'companyName',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelCompanyName', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelCompanyName', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigcompanyemail',
                        name: 'companyEmail',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelCompanyEmail', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelCompanyEmail', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigcompanyurl',
                        name: 'companyUrl',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelCompanyUrl', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelCompanyUrl', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigtaxnumber',
                        name: 'taxNumber',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelTaxNumber', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelTaxNumber', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigvatid',
                        name: 'vatId',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelVatId', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelVatId', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigbankname',
                        name: 'bankName',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelBankName', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelBankName', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigiban',
                        name: 'bankIban',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelBankIban', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelBankIban', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigbic',
                        name: 'bankBic',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelBankBic', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelBankBic', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigcompanyplaceofjurisdiction',
                        name: 'placeOfJurisdiction',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelPlaceOfJurisdiction', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelPlaceOfJurisdiction', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigplaceoffulillment',
                        name: 'placeOfFulfillment',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelPlaceOfFulfillment', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelPlaceOfFulfillment', 'de-DE')
                            }
                        }
                    },
                    {
                        id: 'docconfigexecutivedirector',
                        name: 'executiveDirector',
                        type: 'text',
                        config: {
                            type: 'text',
                            label: {
                                'en-GB': this.$t('sw-settings-document.detail.labelExecutiveDirector', 'en-GB'),
                                'de-DE': this.$t('sw-settings-document.detail.labelExecutiveDirector', 'de-DE')
                            }
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
            return State.getStore('document_base_config_sales_channel');
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
                this.onChangeType(this.documentConfig.documentTypeId);
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
            if (!id) {
                this.selectedType = {};
                return;
            }
            this.selectedType = this.documentTypeStore.getById(id);
            const documentSalesChannels = this.repositoryFactory.create('document_base_config_sales_channel');
            const documentSalesChannelCriteria = new Criteria();
            documentSalesChannelCriteria.addFilter(
                Criteria.equals('documentTypeId', id)
            );
            documentSalesChannels.search(documentSalesChannelCriteria, this.context).then((responseSalesChannels) => {
                const assignedSalesChannelIds = [];
                responseSalesChannels.forEach((salesChannel) => {
                    if (salesChannel.salesChannelId !== null) {
                        assignedSalesChannelIds.push(salesChannel.salesChannelId);
                    }
                });
                this.getPossibleSalesChannels(assignedSalesChannelIds);
            });
        },
        getPossibleSalesChannels(assignedSalesChannelIds) {
            this.setSalesChannelCriteria(assignedSalesChannelIds);
            if (this.documentConfig.global === false) {
                this.documentBaseConfigSalesChannelAssociationStore.getList({
                    associations: { salesChannel: {} }
                }).then((responseAssoc) => {
                    this.enrichAssocStores(responseAssoc);
                });
            }
        },
        setSalesChannelCriteria(assignedSalesChannelIds) {
            this.salesChannelsTypeCriteria = null;
            if (assignedSalesChannelIds.length > 0) {
                // get all salesChannels which are not assigned to this documentType
                // and all SalesChannels already assigned to the current DocumentConfig if type not changed
                if (this.documentConfig.documentTypeId === this.selectedType.id) {
                    this.salesChannelsTypeCriteria = CriteriaFactory.multi('OR',
                        CriteriaFactory.equals('documentBaseConfigSalesChannels.id', null),
                        CriteriaFactory.not(
                            'AND',
                            CriteriaFactory.equalsAny('id', assignedSalesChannelIds)
                        ),
                        CriteriaFactory.equals(
                            'documentBaseConfigSalesChannels.documentBaseConfig.id', this.documentConfig.id
                        ));
                } else { // type changed so only get free saleschannels
                    this.salesChannelsTypeCriteria = CriteriaFactory.multi('OR',
                        CriteriaFactory.equals('documentBaseConfigSalesChannels.id', null),
                        CriteriaFactory.not(
                            'AND',
                            CriteriaFactory.equalsAny('id', assignedSalesChannelIds)
                        ));
                }
            }
        },
        enrichAssocStores(responseAssoc) {
            this.documentConfigSalesChannels = [];
            this.documentConfigSalesChannelsAssoc = responseAssoc;
            this.documentConfigSalesChannelsAssoc.items.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId !== null) {
                    this.documentConfigSalesChannelsStore.add(salesChannelAssoc.salesChannel);
                    this.documentConfigSalesChannels.push(salesChannelAssoc.salesChannel.id);
                }
            });
            if (this.$refs.documentSalesChannel) {
                this.$refs.documentSalesChannel.loadSelected(true);
            }
        },
        onChangeSalesChannel() {
            if (this.$refs.documentSalesChannel) {
                this.$refs.documentSalesChannel.updateValue();
            }
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
