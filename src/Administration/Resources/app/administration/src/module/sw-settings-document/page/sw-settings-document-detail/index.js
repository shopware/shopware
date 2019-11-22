import LocalStore from 'src/core/data/LocalStore';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-document-detail.html.twig';
import './sw-settings-document-detail.scss';

const { Component, StateDeprecated, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

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

    data() {
        return {
            documentConfig: {},
            documentConfigSalesChannelsStore: {},
            documentConfigSalesChannels: [],
            documentConfigSalesChannelsAssoc: {},
            isLoading: false,
            isSaveSuccessful: false,
            salesChannels: {},
            salesChannelsTypeCriteria: {},
            selectedType: {},
            generalFormFields: [
                {
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
                    name: 'itemsPerPage',
                    type: 'text',
                    config: {
                        type: 'text',
                        label: {
                            'en-GB': this.$t('sw-settings-document.detail.labelItemsPerPage', 'en-GB'),
                            'de-DE': this.$t('sw-settings-document.detail.labelItemsPerPage', 'de-DE')
                        }
                    }
                }
            ],
            companyFormFields: [
                {
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
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.documentConfig.name;
        },

        documentBaseConfigStore() {
            return StateDeprecated.getStore('document_base_config');
        },

        documentTypeStore() {
            return StateDeprecated.getStore('document_type');
        },

        salesChannelStore() {
            return StateDeprecated.getStore('sales_channel');
        },

        documentBaseConfigSalesChannelAssociationStore() {
            return this.documentConfig.getAssociation('salesChannels');
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
            this.salesChannelStore.getList({}).then((response) => {
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
            documentSalesChannels.search(documentSalesChannelCriteria, Shopware.Context.api)
                .then((responseSalesChannels) => {
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

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.onChangeSalesChannel();

            return this.documentConfig.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.document.index' });
        }
    }
});
