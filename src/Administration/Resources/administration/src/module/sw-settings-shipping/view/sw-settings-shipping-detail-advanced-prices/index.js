import { Component, State } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import utils from 'src/core/service/util.service';
import template from './sw-settings-shipping-detail-advanced-prices.html.twig';
import './sw-settings-shipping-detail-advanced-prices.scss';

Component.register('sw-settings-shipping-detail-advanced-prices', {
    template,
    props: {
        shippingMethod: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean
        }
    },
    data() {
        return {
            currencies: [],
            rules: [],
            totalRules: 0,
            isLoadingRules: false
        };
    },
    created() {
        this.createdComponent();
    },
    computed: {
        ruleFilter() {
            return CriteriaFactory.multi('OR',
                CriteriaFactory.contains('rule.moduleTypes.types', 'price'),
                CriteriaFactory.equals('rule.moduleTypes', null));
        },

        selectValues() {
            const values = [
                {
                    label: this.$tc('sw-settings-shipping.constants.lineItemCount'),
                    value: 1
                },
                {
                    label: this.$tc('sw-settings-shipping.constants.price'),
                    value: 2
                },
                {
                    label: this.$tc('sw-settings-shipping.constants.weight'),
                    value: 3
                }
            ];

            return new LocalStore(values, 'value');
        },

        priceRuleStore() {
            return this.shippingMethod.getAssociation('priceRules');
        },

        ruleStore() {
            return State.getStore('rule');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        priceRuleGroups() {
            const priceRuleGroups = {};

            this.shippingMethod.priceRules.forEach((rule) => {
                if (this.priceRuleStore.getById(rule.id).isDeleted) {
                    return;
                }

                if (!priceRuleGroups[rule.ruleId]) {
                    priceRuleGroups[rule.ruleId] = {
                        ruleId: rule.ruleId,
                        rule: this.findRuleById(rule.ruleId),
                        currencies: {},
                        calculation: rule.calculation
                    };
                }

                if (!priceRuleGroups[rule.ruleId].currencies[rule.currencyId]) {
                    priceRuleGroups[rule.ruleId].currencies[rule.currencyId] = {
                        currencyId: rule.currencyId,
                        currency: this.findCurrencyById(rule.currencyId),
                        prices: []
                    };
                }

                priceRuleGroups[rule.ruleId].currencies[rule.currencyId].prices.push(rule);
            });

            return priceRuleGroups;
        },

        canAddPriceRule() {
            const usedRules = Object.keys(this.priceRuleGroups).length;
            const availableRules = this.rules.length;

            return usedRules !== availableRules;
        },

        isLoaded() {
            return !this.isLoading &&
                !this.isLoadingRules &&
                this.currencies.length &&
                this.shippingMethod;
        },
        defaultCurrency() {
            return this.currencies.find((currency) => {
                return currency.isDefault;
            });
        }
    },

    methods: {
        createId() {
            return utils.createId();
        },
        createdComponent() {
            this.shippingMethod.getAssociation('priceRules').getList({
                page: 1,
                limit: 500
            });
            this.isLoadingRules = true;

            this.ruleStore.getList({
                page: 1,
                limit: 500,
                criteria: this.ruleFilter
            }).then((response) => {
                this.rules = response.items;
                this.totalRules = response.total;

                this.isLoadingRules = false;
            });

            this.currencyStore.getList({
                page: 1,
                limit: 500
            }).then((response) => {
                this.currencies = response.items;
            });
        },

        onRuleChange(value, ruleId) {
            this.shippingMethod.priceRules.forEach((priceRule) => {
                if (priceRule.ruleId === ruleId) {
                    priceRule.ruleId = value;
                }
            });
        },

        onAddNewPriceGroup() {
            const newPriceRule = this.priceRuleStore.create();
            newPriceRule.shippingMethodId = this.shippingMethod.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.calculation = 1;
            newPriceRule.currencyId = this.defaultCurrency.id;

            this.shippingMethod.priceRules.push(newPriceRule);
        },

        onAddCurrency(ruleId, currency) {
            const defaultCurrencyPrices = this.priceRuleGroups[ruleId].currencies[this.defaultCurrency.id].prices;

            defaultCurrencyPrices.forEach((price) => {
                const newPriceRule = this.priceRuleStore.duplicate(price.id);

                newPriceRule.currencyId = currency.id;
                this.shippingMethod.priceRules.push(newPriceRule);
            });
        },

        onPriceGroupDelete(ruleId) {
            this.priceRuleStore.forEach((item) => {
                if (item.ruleId === ruleId) {
                    item.delete();
                }
            });

            this.shippingMethod.priceRules = this.shippingMethod.priceRules.filter((priceRule) => {
                return priceRule.ruleId !== ruleId;
            });
        },

        onPriceGroupDuplicate(priceGroup) {
            Object.keys(priceGroup.currencies).forEach((currencyId) => {
                priceGroup.currencies[currencyId].prices.forEach((price) => {
                    const newPriceRule = this.priceRuleStore.duplicate(price.id);
                    newPriceRule.ruleId = null;

                    this.shippingMethod.priceRules.push(newPriceRule);
                });
            });
        },

        onPriceRuleDuplicate(priceRule) {
            const newPriceRule = this.priceRuleStore.duplicate(priceRule.id);
            this.shippingMethod.priceRules.push(newPriceRule);
        },

        onPriceRuleDelete(priceRule) {
            // Do not delete the last price of the default currency
            if (priceRule.currencyId === this.defaultCurrency.id) {
                const priceRuleGroup = this.priceRuleGroups[priceRule.ruleId];
                const defaultCurrencyPrices = priceRuleGroup.currencies[this.defaultCurrency.id].prices;
                if (defaultCurrencyPrices.length <= 1 && Object.keys(priceRuleGroup.currencies).length > 1) {
                    return;
                }
            }

            this.shippingMethod.priceRules = this.shippingMethod.priceRules.filter((price) => {
                return price.id !== priceRule.id;
            });

            this.priceRuleStore.getById(priceRule.id).delete();
        },

        onQuantityEndChange(value, price, priceGroup) {
            const currencyPrices = priceGroup.currencies[price.currencyId].prices;

            if (!currencyPrices.length) {
                return;
            }

            if (currencyPrices[currencyPrices.length - 1].id === price.id && value !== null) {
                const newPriceRule = this.priceRuleStore.create();

                newPriceRule.shippingMethodId = this.shippingMethod.id;
                newPriceRule.ruleId = priceGroup.ruleId;
                newPriceRule.quantityStart = price.quantityEnd + 1;
                newPriceRule.quantityEnd = null;
                newPriceRule.currencyId = price.currencyId;

                this.shippingMethod.priceRules.push(newPriceRule);
            }
        },

        calculationStartLabel(calculation) {
            const calculationType = {
                1: this.$tc('sw-settings-shipping.priceRules.columnQuantityStart'),
                2: this.$tc('sw-settings-shipping.priceRules.columnPriceStart'),
                3: this.$tc('sw-settings-shipping.priceRules.columnWeightStart')
            };

            return calculationType[calculation] || this.$tc('sw-settings-shipping.priceRules.columnQuantityStart');
        },

        calculationEndLabel(calculation) {
            const calculationType = {
                1: this.$tc('sw-settings-shipping.priceRules.columnQuantityEnd'),
                2: this.$tc('sw-settings-shipping.priceRules.columnPriceEnd'),
                3: this.$tc('sw-settings-shipping.priceRules.columnWeightEnd')
            };

            return calculationType[calculation] || this.$tc('sw-settings-shipping.priceRules.columnQuantityEnd');
        },

        onCalculationChange(calculation, ruleId) {
            if (!ruleId) {
                return;
            }
            const priceRules = this.shippingMethod.priceRules.filter(priceRule => priceRule.ruleId === ruleId);
            priceRules.forEach(priceRule => { priceRule.calculation = Number(calculation); });

            if (calculation === 1) {
                priceRules.forEach(priceRule => {
                    if (priceRule.quantityStart === null || priceRule.quantityStart < 1) {
                        priceRule.quantityStart = 1;
                    }
                });
            } else {
                priceRules.forEach(priceRule => {
                    if (priceRule.quantityStart === null) {
                        priceRule.quantityStart = 0;
                    }
                });
            }
        },

        findRuleById(ruleId) {
            return this.rules.find((rule) => {
                return rule.id === ruleId;
            });
        },

        findCurrencyById(currencyId) {
            return this.currencies.find((currency) => {
                return currency.id === currencyId;
            });
        }
    }
});
