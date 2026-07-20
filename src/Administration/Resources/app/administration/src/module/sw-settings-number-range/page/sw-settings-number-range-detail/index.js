/**
 * @sw-package inventory
 */
import template from './sw-settings-number-range-detail.html.twig';
import './sw-settings-number-range-detail.scss';

const {
    Component,
    Mixin,
    Data: { Criteria, EntityCollection },
} = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'numberRangeService',
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            numberRangeId: undefined,
            numberRange: {},
            selectedSalesChannelsCollection: null,
            advanced: false,
            simplePossible: true,
            prefix: '',
            suffix: '',
            preview: '',
            state: 1,
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.numberRange, 'name');
        },

        disableNumberRangeTypeSelect() {
            return (
                this.numberRange.type.global ||
                this.numberRange.global ||
                (this.numberRange.type !== null &&
                    this.numberRange.numberRangeSalesChannels &&
                    this.numberRange.numberRangeSalesChannels.length > 0) ||
                !this.acl.can('number_ranges.editor')
            );
        },

        numberRangeRepository() {
            return this.repositoryFactory.create('number_range');
        },

        numberRangeCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('type');
            criteria.addAssociation('numberRangeSalesChannels.salesChannel');

            return criteria;
        },

        numberRangeTypeRepository() {
            return this.repositoryFactory.create('number_range_type');
        },

        numberRangeTypeCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('global', false));

            criteria.addSorting(Criteria.sort('typeName', 'ASC'));

            return criteria;
        },

        numberRangeTypeCriteriaGlobal() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('global', true));

            criteria.addSorting(Criteria.sort('typeName', 'ASC'));

            return criteria;
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.equals('numberRangeSalesChannels.numberRange.id', this.numberRange.id),
                    Criteria.not('OR', [
                        Criteria.equals('numberRangeSalesChannels.numberRangeTypeId', this.numberRange.typeId),
                    ]),
                ]),
            );

            return criteria;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        numberRangeSalesChannelsRepository() {
            return this.repositoryFactory.create('number_range_sales_channel');
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use selectedSalesChannelsCollection instead
         */
        selectedNumberRangeSalesChannels() {
            if (!this.numberRange.numberRangeSalesChannels) {
                return [];
            }

            return this.numberRange.numberRangeSalesChannels.map((numberRangeSalesChannel) => {
                return numberRangeSalesChannel.salesChannelId;
            });
        },

        tooltipSave() {
            if (!this.acl.can('number_ranges.editor')) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('number_ranges.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },

        showNumberRangeStateFields() {
            return !!this.numberRange.id && this.numberRange.isLoading !== true;
        },

        ...mapPropertyErrors('numberRange', [
            'name',
            'typeId',
        ]),

        stateInput: {
            get() {
                return String(this.state);
            },

            set(value) {
                this.state = Number(value);
            },
        },

        previewInput: {
            get() {
                return String(this.preview);
            },

            set(value) {
                this.preview = Number(value);
            },
        },
    },

    watch: {
        'numberRange.pattern'() {
            this.getPreview();
        },
        'numberRange.start'() {
            this.getPreview();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            if (this.$route.params.id && this.numberRange.isLoading !== true) {
                this.numberRangeId = this.$route.params.id.toLowerCase();
                await Promise.all([
                    this.loadEntityData(),
                    this.loadCustomFieldSets(),
                ]);
            }

            this.isLoading = false;
        },

        async loadEntityData() {
            this.numberRange = await this.numberRangeRepository.get(
                this.numberRangeId,
                Shopware.Context.api,
                this.numberRangeCriteria,
            );

            this.getState();
            this.splitPattern();
            await this.loadSalesChannels();
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('number_range').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        splitPattern() {
            if (this.numberRange.pattern === '') {
                return;
            }
            const regex = /([^{}]*)({[^{}]*?})([^{}]*)/gi;
            const patternCheck = regex.exec(this.numberRange.pattern);
            if (
                patternCheck &&
                patternCheck.length === 4 &&
                patternCheck[2] === '{n}' &&
                this.numberRange.pattern.match(regex).length === 1
            ) {
                // valid for simpleFormat
                this.prefix = patternCheck[1] ? patternCheck[1] : '';
                this.suffix = patternCheck[3] ? patternCheck[3] : '';
                this.simplePossible = true;
            } else {
                this.advanced = true;
                this.simplePossible = false;
            }
        },

        getPreview() {
            if (!this.showNumberRangeStateFields) {
                return Promise.resolve();
            }

            return this.numberRangeService
                .previewPatternByNumberRangeId(this.numberRange.id, this.numberRange.pattern, this.numberRange.start)
                .then((response) => {
                    this.preview = response.number;
                });
        },

        getState() {
            if (!this.showNumberRangeStateFields) {
                return Promise.resolve();
            }

            return this.numberRangeService.previewPatternByNumberRangeId(this.numberRange.id, '{n}', 0).then((response) => {
                if (response.number > 1) {
                    this.state = response.number - 1;
                    return Promise.resolve();
                }

                this.state = this.numberRange.start;
                return Promise.resolve();
            });
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use buildSelectedSalesChannelsCollection instead
         */
        loadSalesChannels() {
            this.buildSelectedSalesChannelsCollection();
        },

        buildSelectedSalesChannelsCollection() {
            const collection = new EntityCollection(
                '/sales-channel',
                'sales_channel',
                Shopware.Context.api,
                new Criteria(1, 25),
            );

            if (this.numberRange.numberRangeSalesChannels) {
                this.numberRange.numberRangeSalesChannels.forEach((junction) => {
                    if (junction.salesChannel) {
                        collection.add(junction.salesChannel);
                    }
                });
            }

            this.selectedSalesChannelsCollection = collection;
        },

        onSave() {
            if (!this.acl.can('number_ranges.editor')) {
                return false;
            }

            this.isSaveSuccessful = false;

            const numberRangeName = this.numberRange.name || this.placeholder(this.numberRange, 'name');

            this.onChangePattern();

            if (!this.numberRange.pattern) {
                this.createNotificationError({
                    message: this.$t('sw-settings-number-range.detail.errorPatternNeededMessage'),
                });
                return false;
            }

            if (this.state > 1 && this.state >= this.numberRange.start) {
                this.createNotificationInfo({
                    message: this.$t('sw-settings-number-range.detail.infoStartDecrementMessage'),
                });
            }

            this.isLoading = true;

            return this.numberRangeRepository
                .save(this.numberRange)
                .then(() => {
                    this.isSaveSuccessful = true;

                    return this.loadEntityData();
                })
                .catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$t('sw-settings-number-range.detail.messageSaveError', { name: numberRangeName }, 0),
                    });
                    throw exception;
                })
                .finally(() => {
                    this.isLoading = false;
                    this.getState();
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.number.range.index' });
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        abortOnLanguageChange() {
            return this.numberRangeRepository.hasChanges(this.numberRange);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangePattern() {
            if (this.prefix === null) {
                this.prefix = '';
            }

            if (this.suffix === null) {
                this.suffix = '';
            }

            if (this.advanced !== true) {
                this.numberRange.pattern = `${this.prefix}{n}${this.suffix}`;
                return;
            }

            this.splitPattern();
        },

        onChangeType() {
            this.loadSalesChannels();
        },

        addSalesChannel(salesChannel) {
            const newNumberRangeSalesChannel = this.numberRangeSalesChannelsRepository.create();

            newNumberRangeSalesChannel.numberRangeId = this.numberRange.id;
            newNumberRangeSalesChannel.numberRangeTypeId = this.numberRange.typeId;
            newNumberRangeSalesChannel.salesChannelId = salesChannel.id;
            newNumberRangeSalesChannel.salesChannel = salesChannel;

            this.numberRange.numberRangeSalesChannels.push(newNumberRangeSalesChannel);
            this.buildSelectedSalesChannelsCollection();
        },

        removeSalesChannel(salesChannel) {
            const numberRangeSalesChannelToRemove = this.numberRange.numberRangeSalesChannels.find((nRsalesChannel) => {
                return nRsalesChannel.salesChannelId === salesChannel.id;
            });

            if (numberRangeSalesChannelToRemove) {
                this.numberRange.numberRangeSalesChannels.remove(numberRangeSalesChannelToRemove.id);
            }

            this.buildSelectedSalesChannelsCollection();
        },

        noSalesChannelSelected() {
            return (
                this.numberRange.global === false &&
                (this.numberRange.type.global === false || this.numberRange.type.global === null) &&
                (!this.numberRange.numberRangeSalesChannels || this.numberRange.numberRangeSalesChannels.length === 0)
            );
        },
    },
};
