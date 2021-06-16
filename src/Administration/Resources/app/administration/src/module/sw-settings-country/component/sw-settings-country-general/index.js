import template from './sw-settings-country-general.html.twig';
import './sw-settings-country-general.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;


Component.register('sw-settings-country-general', {
    template,
    flag: 'FEATURE_NEXT_14114',

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('country'),
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        userConfig: {
            type: Object,
            required: true,
        },
        userConfigValues: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            showCurrencyModal: false,
            currencyDependsValue: [],
            currencies: [],
            menuOptions: [],
            taxFreeType: '',
            countryId: '',
            baseCurrencyId: '',
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        ...mapPropertyErrors('country', ['name']),

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }
            this.countryId = this.$route.params.id;
            this.loadCurrencies();
        },

        loadCurrencies() {
            return this.currencyRepository.search(new Criteria(), Shopware.Context.api).then(currencies => {
                this.currencies = currencies;
            });
        },

        openCustomerTaxModal() {
            this.onToggleCurrencyModal('customerTax');
        },

        openCompanyTaxModal() {
            this.onToggleCurrencyModal('companyTax');
        },

        onToggleCurrencyModal(taxFreeType) {
            this.taxFreeType = taxFreeType;
            this.showCurrencyModal = !this.showCurrencyModal;
            if (this.showCurrencyModal) {
                this.createDataModal();
                return;
            }
            this.clearMenuOptions();
        },

        changeBaseItem(item) {
            if (!['customerTax', 'companyTax'].includes(this.taxFreeType)) {
                return;
            }
            this.country[this.taxFreeType] = item;
        },

        createDataModal() {
            if (this.taxFreeType === 'companyTax' || this.taxFreeType === 'customerTax') {
                this.currencyDependsValue = [this.country[this.taxFreeType]];
            }

            this.baseCurrencyId = this.currencyDependsValue[0].currencyId;

            if (Object.keys(this.userConfigValues).length > 0) {
                this.pushDataFromUserConfig();
            }

            this.addCheckedHamburgerMenu();
            this.addDisabledBaseCurrencyCheckBox();
            this.sortCurrencyCheckBox();
        },

        clearMenuOptions() {
            this.menuOptions.forEach(checkBox => {
                delete checkBox.checked;
                delete checkBox.disabled;
            });
        },

        addCheckedHamburgerMenu() {
            this.menuOptions = [...this.currencies];

            this.menuOptions.forEach((checkBox) => {
                const checked = this.currencyDependsValue.find((value) => {
                    return checkBox.id === value.currencyId;
                });

                checkBox.checked = !!checked;
            });
        },

        addDisabledBaseCurrencyCheckBox() {
            this.menuOptions.forEach((checkBox) => {
                checkBox.disabled = checkBox.id === this.baseCurrencyId;
            });
        },

        sortCurrencyCheckBox() {
            this.menuOptions.sort((a, b) => b.checked - a.checked || b.disabled - a.disabled);
        },

        pushDataFromUserConfig() {
            if (!this.userConfigValues[this.taxFreeType]) {
                return;
            }
            this.userConfigValues[this.taxFreeType].forEach((currencyId) => {
                const userCurrencyDependsValue = {};
                userCurrencyDependsValue.amount = this.calculateInheritedPrice(currencyId);
                userCurrencyDependsValue.currencyId = currencyId;
                userCurrencyDependsValue.enabled = false;
                const existedValue = this.currencyDependsValue.find(value => {
                    return value.currencyId === currencyId;
                });

                if (!existedValue) {
                    this.currencyDependsValue.push(userCurrencyDependsValue);
                }
            });
        },

        calculateInheritedPrice(currencyId) {
            const basePrice = this.currencyDependsValue.find((value) => {
                return value.enabled === true;
            });
            if (!basePrice) {
                return 0;
            }
            return this.getPriceByCurrency(basePrice, currencyId);
        },

        getPriceByCurrency(basePrice, currencyId) {
            const currency = this.getCurrencyById(currencyId);
            const currencyBaseItem = this.getCurrencyById(basePrice.currencyId);

            if (!currencyBaseItem.factor || !currency.factor) {
                return 0;
            }

            return (basePrice.amount / currencyBaseItem.factor) * currency.factor;
        },

        getCurrencyById(currencyId) {
            const currency = this.currencies.find((value) => {
                return value.id === currencyId;
            });

            return currency || {};
        },

        saveCountryCurrencyDependent() {
            this.$emit('modal-save');
        },
    },
});
