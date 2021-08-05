import template from './sw-settings-currency-detail.html.twig';
import './sw-settings-currency-detail.scss';

const { cloneDeep } = Shopware.Utils.object;
const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-currency-detail', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature', 'customFieldDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        currencyId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('currencies.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            currency: {},
            isLoading: false,
            currencyCountryLoading: false,
            isSaveSuccessful: false,
            currentCurrencyCountry: null,
            currencyCountryRoundings: null,
            searchTerm: '',
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
            return this.placeholder(this.currency, 'name');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        currencyCountryRoundingRepository() {
            if (this.currency.countryRoundings) {
                return this.repositoryFactory.create(
                    this.currency.countryRoundings.entity,
                    this.currency.countryRoundings.source,
                );
            }
            return null;
        },

        tooltipSave() {
            if (!this.acl.can('currencies.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('currencies.editor'),
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

        ...mapPropertyErrors(
            'currency',
            ['name', 'isoCode', 'shortName', 'symbol', 'isDefault', 'decimalPrecision', 'factor'],
        ),

        currencyCountryColumns() {
            return [
                {
                    property: 'country',
                    label: 'sw-settings-currency.detail.currencyCountry.countryColumn',
                    sortable: true,
                },
                {
                    property: 'itemRounding.decimals',
                    label: 'sw-settings-currency.detail.currencyCountry.itemDecimalsColumn',
                    sortable: false,
                },
                {
                    property: 'itemRounding.interval',
                    label: 'sw-settings-currency.detail.currencyCountry.itemIntervalColumn',
                    sortable: false,
                },
                {
                    property: 'itemRounding.roundForNet',
                    label: 'sw-settings-currency.detail.currencyCountry.itemNetRoundingColumn',
                    sortable: false,
                    visible: false,
                },
                {
                    property: 'totalRounding.decimals',
                    label: 'sw-settings-currency.detail.currencyCountry.totalDecimalsColumn',
                    sortable: false,
                },
                {
                    property: 'totalRounding.interval',
                    label: 'sw-settings-currency.detail.currencyCountry.totalIntervalColumn',
                    sortable: false,
                },
                {
                    property: 'totalRounding.roundForNet',
                    label: 'sw-settings-currency.detail.currencyCountry.totalNetRoundingColumn',
                    sortable: false,
                    visible: false,
                },
            ];
        },

        currencyCountryRoundingCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('country');
            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            } else {
                criteria.setTerm('');
            }

            criteria.addSorting(Criteria.sort('country.name'));

            return criteria;
        },

        emptyStateText() {
            if (this.currency.id && this.currency.isNew()) {
                return this.$tc('sw-settings-currency.detail.emptyCountryRoundingsNewCurrency');
            }

            return this.$tc('sw-settings-currency.detail.emptyCountryRoundings');
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        currencyId() {
            if (!this.currencyId) {
                this.createdComponent();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.currencyId) {
                this.currencyId = this.$route.params.id;
                return Promise.all([
                    this.loadEntityData(),
                    this.loadCustomFieldSets(),
                ]);
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.isLoading = true;
            this.currency = this.currencyRepository.create();
            // defaults for rounding
            this.currency.itemRounding = {
                decimals: 2,
                interval: 0.01,
                roundForNet: true,
            };
            this.currency.totalRounding = {
                decimals: 2,
                interval: 0.01,
                roundForNet: true,
            };

            this.isLoading = false;
            return Promise.resolve();
        },

        loadEntityData() {
            this.isLoading = true;
            return this.currencyRepository.get(this.currencyId)
                .then((currency) => {
                    this.currency = currency;
                    return this.loadCurrencyCountryRoundings().then((currencyCountryRoundings) => {
                        return [currency, currencyCountryRoundings];
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        loadCurrencyCountryRoundings() {
            this.currencyCountryLoading = true;
            return this.currencyCountryRoundingRepository.search(this.currencyCountryRoundingCriteria).then(res => {
                this.currencyCountryRoundings = res;
                return res;
            }).finally(() => {
                this.currencyCountryLoading = false;
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('currency').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.currencyRepository.save(this.currency).then(() => {
                this.isSaveSuccessful = true;
                if (!this.currencyId) {
                    this.$router.push({ name: 'sw.settings.currency.detail', params: { id: this.currency.id } });
                }

                this.currencyRepository.get(this.currency.id).then((updatedCurrency) => {
                    this.currency = updatedCurrency;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-settings-currency.detail.notificationErrorMessage'),
                });
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.currency.index' });
        },

        abortOnLanguageChange() {
            return this.currencyRepository.hasChanges(this.currency);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onChangeCountrySearch(value) {
            this.searchTerm = value;
            this.loadCurrencyCountryRoundings();
        },

        onAddCountry() {
            this.currentCurrencyCountry = this.currencyCountryRoundingRepository.create();
            this.currentCurrencyCountry.itemRounding = cloneDeep(this.currency.itemRounding);
            this.currentCurrencyCountry.totalRounding = cloneDeep(this.currency.totalRounding);
            this.currentCurrencyCountry.currencyId = this.currency.id;
        },

        onCancelEditCountry() {
            this.currentCurrencyCountry = null;
        },

        onClickEdit(item) {
            this.currentCurrencyCountry = item;
        },

        onSaveCurrencyCountry() {
            this.currencyCountryLoading = true;
            this.currencyCountryRoundingRepository.save(this.currentCurrencyCountry).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-settings-currency.detail.notificationCountrySuccessMessage'),
                });
                this.onCancelEditCountry();
                this.loadCurrencyCountryRoundings();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-currency.detail.notificationCountryErrorMessage'),
                });
            }).finally(() => {
                this.currencyCountryLoading = false;
            });
        },
    },
});
