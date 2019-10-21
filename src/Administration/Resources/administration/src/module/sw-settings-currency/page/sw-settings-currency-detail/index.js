import template from './sw-settings-currency-detail.html.twig';

const { Component, Mixin, State } = Shopware;
const { mapApiErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-currency-detail', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    props: {
        currencyId: {
            type: String,
            required: false,
            default: null
        }
    },

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            currency: {},
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.currency, 'name');
        },

        languageStore() {
            return State.getStore('language');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
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
        },

        ...mapApiErrors('currency', ['name', 'isoCode', 'shortName', 'symbol', 'isDefault', 'decimalPrecision', 'factor'])
    },

    watch: {
        currencyId() {
            if (!this.currencyId) {
                this.createdComponent();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.currencyId) {
                this.currencyId = this.$route.params.id;
                this.currencyRepository.get(this.currencyId, this.apiContext).then((currency) => {
                    this.currency = currency;
                    this.isLoading = false;
                });
                return;
            }

            this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            this.currency = this.currencyRepository.create(this.apiContext);
            this.isLoading = false;
        },

        loadEntityData() {
            this.currency = this.currencyRepository.get(this.currencyId, this.apiContext).then((currency) => {
                this.currency = currency;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.currencyRepository.save(this.currency, this.apiContext).then(() => {
                this.isSaveSuccessful = true;
                if (!this.currencyId) {
                    this.$router.push({ name: 'sw.settings.currency.detail', params: { id: this.currency.id } });
                }

                this.currencyRepository.get(this.currency.id, this.apiContext).then((updatedCurrency) => {
                    this.currency = updatedCurrency;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-currency.detail.notificationErrorTitle'),
                    message: this.$tc('sw-settings-currency.detail.notificationErrorMessage')
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
        }
    }
});
