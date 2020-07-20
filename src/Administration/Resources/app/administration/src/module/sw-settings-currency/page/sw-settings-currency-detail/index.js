import template from './sw-settings-currency-detail.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-currency-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('currencies.editor');
            },
            method: 'onSave'
        },
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

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        tooltipSave() {
            if (!this.acl.can('currencies.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('currencies.editor'),
                    showOnDisabledElements: true
                };
            }

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

        ...mapPropertyErrors(
            'currency',
            ['name', 'isoCode', 'shortName', 'symbol', 'isDefault', 'decimalPrecision', 'factor']
        )
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
                this.currencyRepository.get(this.currencyId, Shopware.Context.api).then((currency) => {
                    this.currency = currency;
                    this.isLoading = false;
                });
                return;
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.currency = this.currencyRepository.create(Shopware.Context.api);
            this.isLoading = false;
        },

        loadEntityData() {
            this.currency = this.currencyRepository.get(this.currencyId, Shopware.Context.api).then((currency) => {
                this.currency = currency;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.currencyRepository.save(this.currency, Shopware.Context.api).then(() => {
                this.isSaveSuccessful = true;
                if (!this.currencyId) {
                    this.$router.push({ name: 'sw.settings.currency.detail', params: { id: this.currency.id } });
                }

                this.currencyRepository.get(this.currency.id, Shopware.Context.api).then((updatedCurrency) => {
                    this.currency = updatedCurrency;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
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
