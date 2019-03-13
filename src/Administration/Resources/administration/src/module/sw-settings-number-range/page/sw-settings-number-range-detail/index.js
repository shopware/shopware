import { Component, State, Mixin } from 'src/core/shopware';
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
            advanced: false,
            simplePossible: true,
            prefix: '',
            suffix: '',
            preview: '',
            state: 1
        };
    },

    computed: {
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
            return this.numberRange.getAssociation('salesChannels');
        },
        isGlobal() {
            return (
                this.numberRange.salesChannels
                && (
                    (
                        this.selectedType.id === this.numberRange.type.id
                        && this.numberRange.type.global === false
                    )
                    || this.selectedType.global === false
                )
            );
        },
        firstSalesChannel() {
            if (this.numberRange.salesChannels && this.numberRange.salesChannels.length > 0) {
                return this.numberRange.salesChannels[0].id;
            }
            return '';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.numberRangeId = this.$route.params.id;
                this.loadEntityData();
            }
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
                this.prefix = '';
                this.suffix = '';
                this.advanced = true;
                this.simplePossible = false;
            }
        },

        getPreview() {
            this.numberRangeService.previewPattern(
                this.numberRange.type.typeName,
                this.numberRange.pattern,
                this.numberRange.start
            ).then((response) => {
                this.preview = response.number;
            });
        },

        getState() {
            this.numberRangeService.reserve(
                this.numberRange.type.typeName,
                this.firstSalesChannel,
                true
            ).then((response) => {
                this.state = response.number - 1;
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
            this.numberRangeStore.getByIdAsync(this.numberRangeId).then((response) => {
                this.numberRange = response;
                this.selectedType = this.numberRangeTypeStore.getById(this.numberRange.typeId);
                this.getPreview();
                this.getState();
                this.splitPattern();
            });
        },

        showOption(item) {
            return item.id !== this.numberRange.id;
        },

        onChangeType(id) {
            this.selectedType = this.numberRangeTypeStore.getById(id);
        },

        onSave() {
            const numberRangeName = this.numberRange.name;
            const titleSaveSuccess = this.$tc('sw-settings-number-range.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-number-range.detail.messageSaveSuccess',
                0,
                { name: numberRangeName }
            );
            return this.numberRange.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
