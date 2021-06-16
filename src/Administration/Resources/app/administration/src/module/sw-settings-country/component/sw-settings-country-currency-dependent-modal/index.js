import template from './sw-settings-country-currency-dependent-modal.html.twig';
import './sw-settings-country-currency-dependent-modal.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.register('sw-settings-country-currency-dependent-modal', {
    template,
    flag: 'FEATURE_NEXT_14114',

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
    ],

    props: {
        currencyDependsValue: {
            type: Array,
            required: true,
        },
        countryId: {
            type: String,
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
        menuOptions: {
            type: Array,
            required: true,
        },
        taxFreeType: {
            type: String,
            required: false,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            inputId: utils.createId(),
            checkBox: true,
            basedItem: {},
        };
    },

    computed: {
        currentUserId() {
            return Shopware.State.get('session').currentUser.id;
        },

        currencyTaxFreeDependentRepository() {
            return this.repositoryFactory.create('country_currency_tax_free_dependent_value');
        },

        radioButtonName() {
            return `sw-settings-country-currency-dependent-modal-boolean-radio-${this.inputId}`;
        },

        countryCurrencyColumns() {
            return [{
                property: 'currencyId',
                label: '',
                inlineEdit: 'string',
                primary: true,
            }, {
                property: 'amount',
                label: this.$tc('sw-settings-country.detail.taxFreeFrom'),
                inlineEdit: 'string',
                primary: true,
            }, {
                property: 'enabled',
                label: this.$tc('sw-settings-country.detail.baseCurrency'),
                inlineEdit: 'string',
            }];
        },
    },

    methods: {
        closeModal() {
            this.$emit('modal-close');
        },

        saveModal() {
            this.createUserConfigValue();
            this.closeModal();
            this.$emit('modal-save');
        },

        changeCurrencyDependentRow(currencyId, isChecked) {
            if (isChecked) {
                this.addCurrencyDependentRow(currencyId);
                return;
            }
            this.removeCurrencyDependentRow(currencyId);
        },

        addCurrencyDependentRow(currencyId) {
            const taxFreeDependent = {
                amount: this.calculateInheritedPrice(currencyId),
                enabled: false,
                currencyId: currencyId,
            };

            this.currencyDependsValue.push(taxFreeDependent);
        },

        removeCurrencyDependentRow(currencyId) {
            const currencyDependentRemoval = this.currencyDependsValue.find((value) => {
                return value.currencyId === currencyId;
            });

            if (!currencyDependentRemoval) {
                return;
            }

            this.currencyDependsValue
                .splice(this.currencyDependsValue.indexOf(currencyDependentRemoval), 1);

            if (this.userConfigValues[this.taxFreeType]) {
                this.userConfigValues[this.taxFreeType]
                    .splice(this.userConfigValues[this.taxFreeType].indexOf(currencyId), 1);
            }
            this.updateCheckBoxHamburgerMenu(currencyId);
        },

        updateCheckBoxHamburgerMenu(currencyId) {
            this.menuOptions.forEach((currency) => {
                if (currency.id === currencyId) {
                    currency.checked = false;
                }
            });
        },

        onChangeBaseCurrency(item) {
            item.enabled = true;

            this.currencyDependsValue.forEach((value) => {
                value.enabled = value.currencyId === item.currencyId;
            });

            if (this.userConfigValues[this.taxFreeType]) {
                this.userConfigValues[this.taxFreeType]
                    .splice(this.userConfigValues[this.taxFreeType].indexOf(item.currencyId), 1);
            }

            this.menuOptions.forEach((currency) => {
                currency.disabled = currency.id === item.currencyId;
            });

            this.checkBox = true;
            this.basedItem = item;
            this.$emit('base-item-change', this.basedItem);
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

        reCalculatorInherited(basePrice) {
            this.currencyDependsValue.forEach((item) => {
                if (item.enabled === false) {
                    item.amount = this.getPriceByCurrency(basePrice, item.currencyId);
                }
            });
        },

        getPriceByCurrency(basePrice, currencyId) {
            const currency = this.getCurrencyById(currencyId);
            const currencyBaseItem = this.getCurrencyById(basePrice.currencyId);
            if (!currency.factor || !currencyBaseItem.factor) {
                return 0;
            }

            return (basePrice.amount / currencyBaseItem.factor) * currency.factor;
        },

        createUserConfigValue() {
            if (this.userConfig.isNew()) {
                return this.createNewUserConfig();
            }
            return this.updateExistedValue();
        },

        createNewUserConfig() {
            this.userConfig.value = {
                [this.countryId]:
                    {
                        [this.taxFreeType]: [],
                    },
            };

            this.currencyDependsValue.forEach(value => {
                if (!value.enabled) {
                    this.userConfig.value[this.countryId][this.taxFreeType].push(value.currencyId);
                }
            });
        },

        updateExistedValue() {
            let valuesUserConfig = this.userConfigValues[this.taxFreeType];
            if (!valuesUserConfig) {
                this.userConfigValues[this.taxFreeType] = [];
                valuesUserConfig = this.userConfigValues[this.taxFreeType];
            }

            this.currencyDependsValue.forEach(value => {
                if (!value.enabled) {
                    valuesUserConfig.push(value.currencyId);
                }
            });

            this.userConfig.value[this.countryId][this.taxFreeType] = [...new Set(valuesUserConfig)];
        },

        getCurrencyNameById(currencyId) {
            const currency = this.menuOptions.find((checkBoxCurrency) => {
                return checkBoxCurrency.id === currencyId;
            });

            return currency?.name ?? '';
        },

        getCurrencyById(currencyId) {
            const currency = this.menuOptions.find((checkBoxCurrency) => {
                return checkBoxCurrency.id === currencyId;
            });

            return currency || {};
        },
    },
});
