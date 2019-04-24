import { Component, State, Mixin } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-number-range-detail.html.twig';
import './sw-settings-number-range-detail.scss';

Component.register('sw-settings-number-range-detail', {
    template,

    inject: ['numberRangeService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            numberRange: {},
            selectedType: {},
            typeCriteria: {},
            numberRangeSalesChannelsStore: {},
            numberRangeSalesChannels: [],
            numberRangeSalesChannelsAssoc: {},
            salesChannelsTypeCriteria: {},
            salesChannels: {},
            advanced: false,
            simplePossible: true,
            prefix: '',
            suffix: '',
            preview: '',
            state: 1
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.numberRange, 'name');
        },

        numberRangeStore() {
            return State.getStore('number_range');
        },

        numberRangeTypeStore() {
            return State.getStore('number_range_type');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        salesChannelAssociationStore() {
            return this.numberRange.getAssociation('numberRangeSalesChannels');
        },

        numberRangeStateStore() {
            return State.getStore('number_range_state');
        },

        firstSalesChannel() {
            if (this.numberRange.numberRangeSalesChannels && this.numberRange.numberRangeSalesChannels.length > 0) {
                return this.numberRange.numberRangeSalesChannels[0].salesChannelId;
            }
            return '';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.numberRangeSalesChannelsStore = new LocalStore();
            this.typeCriteria = CriteriaFactory.equals('global', false);
            if (this.$route.params.id && this.numberRange.isLoading !== true) {
                this.numberRangeId = this.$route.params.id;
                this.loadEntityData();
            }
            this.isLoading = false;
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        abortOnLanguageChange() {
            return this.numberRange.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        splitPattern() {
            if (this.numberRange.pattern === '') {
                return;
            }
            const regex = /([^{}]*)({[^{}]*?})([^{}]*)/ig;
            const patternCheck = regex.exec(this.numberRange.pattern);
            if (
                patternCheck
                && patternCheck.length === 4
                && patternCheck[2] === '{n}'
                && this.numberRange.pattern.match(regex).length === 1
            ) {
                // valid for simpleFormat
                this.prefix = (patternCheck[1] ? patternCheck[1] : '');
                this.suffix = (patternCheck[3] ? patternCheck[3] : '');
                this.simplePossible = true;
            } else {
                this.advanced = true;
                this.simplePossible = false;
            }
        },

        getPreview() {
            this.numberRangeService.previewPattern(
                this.numberRange.type.technicalName,
                this.numberRange.pattern,
                this.numberRange.start
            ).then((response) => {
                this.preview = response.number;
            });
        },

        getState() {
            this.numberRangeStateStore.getList({
                criteria: CriteriaFactory.equals('numberRangeId', this.numberRangeId)
            }).then((response) => {
                if (response.total === 1) {
                    this.state = response.items[0].lastValue;
                } else {
                    this.state = this.numberRange.start;
                }
            });
        },

        onChange() {
            if (this.prefix === null) {
                this.prefix = '';
            }
            if (this.suffix === null) {
                this.suffix = '';
            }
            if (this.advanced !== true) {
                this.numberRange.pattern = `${this.prefix}{n}${this.suffix}`;
            } else {
                this.splitPattern();
            }
            this.getPreview();
        },

        loadEntityData() {
            this.salesChannelStore.getList().then((response) => {
                this.salesChannels = response;
            });
            this.numberRangeStore.getByIdAsync(this.numberRangeId).then((response) => {
                this.numberRange = response;
                this.onChangeType(this.numberRange.typeId);
                this.getPreview();
                this.getState();
                this.splitPattern();
            });
        },

        showOption(item) {
            return item.id !== this.numberRange.id;
        },

        onChangeType(id) {
            if (!id) {
                this.selectedType = {};
                return;
            }
            this.selectedType = this.numberRangeTypeStore.getById(id);
            this.salesChannelsTypeCriteria = CriteriaFactory.multi('OR',
                CriteriaFactory.equals('numberRangeSalesChannels.numberRangeTypeId', null),
                CriteriaFactory.not(
                    'AND',
                    CriteriaFactory.equals('numberRangeSalesChannels.numberRangeTypeId', this.selectedType.id),
                ),
                CriteriaFactory.equals('numberRangeSalesChannels.numberRange.id', this.numberRange.id));
            if (this.numberRange.global === false) {
                this.salesChannelAssociationStore.getList({
                    associations: { salesChannel: {} }
                }).then((responseAssoc) => {
                    this.numberRangeSalesChannels = [];
                    // get all salesChannels which are not assigned to this numberRangeType
                    // and all SalesChannels already assigned to the current NumberRange
                    this.numberRangeSalesChannelsAssoc = responseAssoc;
                    this.numberRangeSalesChannelsAssoc.items.forEach((salesChannelAssoc) => {
                        if (salesChannelAssoc.salesChannelId !== null) {
                            this.numberRangeSalesChannelsStore.add(salesChannelAssoc.salesChannel);
                            this.numberRangeSalesChannels.push(salesChannelAssoc.salesChannel.id);
                        }
                    });
                    if (this.$refs.numberRangeSalesChannel) {
                        this.$refs.numberRangeSalesChannel.loadSelected(true);
                    }
                });
            }
        },
        onChangeSalesChannel() {
            if (this.$refs.numberRangeSalesChannel) {
                this.$refs.numberRangeSalesChannel.updateValue();
            }
            if (Object.keys(this.numberRange).length === 0) {
                return;
            }
            // check selected saleschannels and associate to config
            if (this.numberRangeSalesChannels && this.numberRangeSalesChannels.length > 0) {
                this.numberRangeSalesChannels.forEach((salesChannel) => {
                    if (!this.configHasSaleschannel(salesChannel)) {
                        const assocConfig = this.salesChannelAssociationStore.create();
                        assocConfig.numberRangeId = this.numberRange.id;
                        assocConfig.numberRangeTypeId = this.selectedType.id;
                        assocConfig.salesChannelId = salesChannel;
                    } else {
                        this.undeleteSaleschannel(salesChannel);
                    }
                });
            }
            this.salesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (!this.selectHasSaleschannel(salesChannelAssoc.salesChannelId)) {
                    salesChannelAssoc.delete();
                }
            });
        },

        configHasSaleschannel(salesChannelId) {
            let found = false;
            this.salesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId) {
                    found = true;
                }
            });
            return found;
        },

        selectHasSaleschannel(salesChannelId) {
            return (this.numberRangeSalesChannels && this.numberRangeSalesChannels.indexOf(salesChannelId) !== -1);
        },

        undeleteSaleschannel(salesChannelId) {
            this.salesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId && salesChannelAssoc.isDeleted === true) {
                    salesChannelAssoc.isDeleted = false;
                }
            });
        },

        onSave() {
            this.onChangeSalesChannel();
            const numberRangeName = this.numberRange.name;
            this.onChange();
            if (this.noSalesChannelSelected()) {
                this.createNotificationError(
                    {
                        title: this.$tc('sw-settings-number-range.detail.errorSalesChannelNeededTitle'),
                        message: this.$tc('sw-settings-number-range.detail.errorSalesChannelNeededMessage')
                    }
                );
                return false;
            }
            if (!this.numberRange.pattern) {
                this.createNotificationError(
                    {
                        title: this.$tc('sw-settings-number-range.detail.errorPatternNeededTitle'),
                        message: this.$tc('sw-settings-number-range.detail.errorPatternNeededMessage')
                    }
                );
                return false;
            }
            const titleSaveSuccess = this.$tc('sw-settings-number-range.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-number-range.detail.messageSaveSuccess',
                0,
                { name: numberRangeName }
            );
            const titleSaveError = this.$tc('sw-settings-number-range.detail.titleSaveError');
            const messageSaveError = this.$tc(
                'sw-settings-number-range.detail.messageSaveError',
                0,
                { name: numberRangeName }
            );
            return this.numberRange.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                throw exception;
            });
        },

        noSalesChannelSelected() {
            return (
                (
                    this.numberRange.global === false &&
                    (
                        this.numberRange.type.global === false ||
                        this.numberRange.type.global === null
                    )
                ) &&
                (
                    !this.numberRangeSalesChannels ||
                    this.numberRangeSalesChannels.length === 0
                )
            );
        }
    }
});
