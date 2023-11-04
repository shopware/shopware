/**
 * @package system-settings
 */
import template from './sw-settings-number-range-detail.html.twig';
import './sw-settings-number-range-detail.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;
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
            salesChannels: [],
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
            return this.numberRange.type.global ||
              this.numberRange.global ||
              (
                  this.numberRange.type !== null &&
                  this.numberRange.numberRangeSalesChannels &&
                  this.numberRange.numberRangeSalesChannels.length > 0
              ) ||
              !this.acl.can('number_ranges.editor');
        },

        numberRangeRepository() {
            return this.repositoryFactory.create('number_range');
        },

        numberRangeCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('type');
            criteria.addAssociation('numberRangeSalesChannels');

            return criteria;
        },

        numberRangeTypeRepository() {
            return this.repositoryFactory.create('number_range_type');
        },

        numberRangeTypeCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.equals('global', false),
            );

            criteria.addSorting(
                Criteria.sort('typeName', 'ASC'),
            );

            return criteria;
        },

        numberRangeTypeCriteriaGlobal() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.equals('global', true),
            );

            criteria.addSorting(
                Criteria.sort('typeName', 'ASC'),
            );

            return criteria;
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.multi(
                    'OR',
                    [
                        Criteria.equals('numberRangeSalesChannels.numberRange.id', this.numberRange.id),
                        Criteria.not(
                            'OR',
                            [
                                Criteria.equals('numberRangeSalesChannels.numberRangeTypeId', this.numberRange.typeId),
                            ],
                        ),
                    ],
                ),
            );

            criteria.addAssociation('numberRangeSalesChannels');

            return criteria;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        numberRangeSalesChannelsRepository() {
            return this.repositoryFactory.create('number_range_sales_channel');
        },

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
                    message: this.$tc('sw-privileges.tooltip.warning'),
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

        ...mapPropertyErrors('numberRange', ['name', 'typeId']),
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
                this.numberRangeId = this.$route.params.id;
                await Promise.all([this.loadEntityData(), this.loadCustomFieldSets()]);
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
            return this.numberRangeService.previewPattern(
                this.numberRange.type.technicalName,
                this.numberRange.pattern,
                this.numberRange.start,
            ).then((response) => {
                this.preview = response.number;
            });
        },

        getState() {
            return this.numberRangeService.previewPattern(
                this.numberRange.type.technicalName,
                '{n}',
                0,
            ).then((response) => {
                if (response.number > 1) {
                    this.state = response.number - 1;
                    return Promise.resolve();
                }

                this.state = this.numberRange.start;
                return Promise.resolve();
            });
        },

        loadSalesChannels() {
            return this.salesChannelRepository.search(this.salesChannelCriteria)
                .then((salesChannel) => {
                    this.salesChannels = salesChannel;
                });
        },

        onSave() {
            if (!this.acl.can('number_ranges.editor')) {
                return false;
            }

            this.isSaveSuccessful = false;

            const numberRangeName = this.numberRange.name || this.placeholder(this.numberRange, 'name');

            this.onChangePattern();

            if (!this.numberRange.pattern) {
                this.createNotificationError(
                    {
                        message: this.$tc('sw-settings-number-range.detail.errorPatternNeededMessage'),
                    },
                );
                return false;
            }

            if (this.state > 1 && this.state >= this.numberRange.start) {
                this.createNotificationInfo(
                    {
                        message: this.$tc('sw-settings-number-range.detail.infoStartDecrementMessage'),
                    },
                );
            }

            this.isLoading = true;

            return this.numberRangeRepository.save(this.numberRange).then(() => {
                this.isSaveSuccessful = true;
            })
                .catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$tc('sw-settings-number-range.detail.messageSaveError', 0, { name: numberRangeName }),
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

            this.numberRange.numberRangeSalesChannels.push(newNumberRangeSalesChannel);

            // fix select gets out of view
            if (this.numberRange.numberRangeSalesChannels.length <= 1) {
                this.$nextTick().then(() => {
                    const scrollableArea = document.querySelector('.sw-card-view__content');

                    if (scrollableArea) {
                        scrollableArea.scrollTop += 78;
                    }
                });
            }
        },

        removeSalesChannel(salesChannel) {
            const numberRangeSalesChannelToRemove = this.numberRange.numberRangeSalesChannels.find((nRsalesChannel) => {
                return nRsalesChannel.salesChannelId === salesChannel.id;
            });

            this.numberRange.numberRangeSalesChannels.remove(numberRangeSalesChannelToRemove.id);
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
                    !this.numberRange.numberRangeSalesChannels ||
                    this.numberRange.numberRangeSalesChannels.length === 0
                )
            );
        },
    },
};
