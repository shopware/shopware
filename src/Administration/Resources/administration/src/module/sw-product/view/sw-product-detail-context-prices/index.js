import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-detail-context-prices.html.twig';
import './sw-product-detail-context-prices.scss';

Component.register('sw-product-detail-context-prices', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        taxes: {
            type: Array,
            required: true,
            default: []
        },
        currencies: {
            type: Array,
            required: true,
            default: []
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            rules: [],
            totalRules: 0,
            ruleFilter: CriteriaFactory.multi(
                'OR',
                CriteriaFactory.contains('rule.moduleTypes.types', 'price'),
                CriteriaFactory.equals('rule.moduleTypes', null)
            ),
            isLoadingRules: false
        };
    },

    computed: {
        priceRuleStore() {
            return this.product.getAssociation('priceRules');
        },

        ruleStore() {
            return State.getStore('rule');
        },

        priceRuleGroups() {
            const priceRuleGroups = {};

            this.product.priceRules.forEach((rule) => {
                if (this.priceRuleStore.getById(rule.id).isDeleted === true) {
                    return;
                }

                if (!priceRuleGroups[rule.ruleId]) {
                    priceRuleGroups[rule.ruleId] = {
                        ruleId: rule.ruleId,
                        rule: this.findRuleById(rule.ruleId),
                        currencies: {}
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
                   this.taxes.length &&
                   this.product;
        },

        productTaxRate() {
            return this.taxes.find((taxRate) => {
                return taxRate.id === this.product.taxId;
            });
        },

        defaultCurrency() {
            return this.currencies.find((currency) => {
                return currency.isDefault;
            });
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.product.getAssociation('priceRules').getList({
                page: 1,
                limit: 500,
                sortBy: 'quantityStart'
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
        },

        onRuleChange(value, ruleId) {
            this.product.priceRules.forEach((priceRule) => {
                if (priceRule.ruleId === ruleId) {
                    priceRule.ruleId = value;
                }
            });
        },

        onAddNewPriceGroup() {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            const newPriceRule = this.priceRuleStore.create();

            newPriceRule.ruleId = null;
            newPriceRule.productId = this.product.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = this.defaultCurrency.id;

            this.product.priceRules.push(newPriceRule);
        },

        onAddCurrency(ruleId, currency) {
            const defaultCurrencyPrices = this.priceRuleGroups[ruleId].currencies[this.defaultCurrency.id].prices;

            defaultCurrencyPrices.forEach((price) => {
                const newPriceRule = this.priceRuleStore.duplicate(price.id);

                newPriceRule.currencyId = currency.id;

                this.product.priceRules.push(newPriceRule);
            });
        },

        onPriceGroupDelete(ruleId) {
            this.priceRuleStore.forEach((item) => {
                if (item.ruleId === ruleId) {
                    item.delete();
                }
            });

            this.product.priceRules = this.product.priceRules.filter((priceRule) => {
                return priceRule.ruleId !== ruleId;
            });
        },

        onPriceGroupDuplicate(priceGroup) {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            Object.keys(priceGroup.currencies).forEach((currencyId) => {
                priceGroup.currencies[currencyId].prices.forEach((price) => {
                    const newPriceRule = this.priceRuleStore.duplicate(price.id);
                    newPriceRule.ruleId = null;

                    this.product.priceRules.push(newPriceRule);
                });
            });
        },

        onPriceRuleDuplicate(priceRule) {
            const newPriceRule = this.priceRuleStore.duplicate(priceRule.id);
            this.product.priceRules.push(newPriceRule);
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

            this.product.priceRules = this.product.priceRules.filter((price) => {
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

                newPriceRule.productId = this.product.id;
                newPriceRule.ruleId = priceGroup.ruleId;
                newPriceRule.quantityStart = price.quantityEnd + 1;
                newPriceRule.quantityEnd = null;
                newPriceRule.currencyId = price.currencyId;

                this.product.priceRules.push(newPriceRule);
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
