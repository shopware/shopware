import { Component, Entity } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import utils from 'src/core/service/util.service';
import template from './sw-product-detail-context-prices.html.twig';
import './sw-product-detail-context-prices.less';

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
            isLoadingRules: false
        };
    },

    computed: {
        priceRuleGroups() {
            const priceRuleGroups = {};

            this.product.priceRules.forEach((ctx) => {
                if (!priceRuleGroups[ctx.ruleId]) {
                    priceRuleGroups[ctx.ruleId] = {
                        ruleId: ctx.ruleId,
                        rule: this.findRuleById(ctx.ruleId),
                        currencies: {}
                    };
                }

                if (!priceRuleGroups[ctx.ruleId].currencies[ctx.currencyId]) {
                    priceRuleGroups[ctx.ruleId].currencies[ctx.currencyId] = {
                        currencyId: ctx.currencyId,
                        currency: this.findCurrencyById(ctx.currencyId),
                        prices: []
                    };
                }

                priceRuleGroups[ctx.ruleId].currencies[ctx.currencyId].prices.push(ctx);
            });

            return priceRuleGroups;
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
            this.ruleStore = Shopware.State.getStore('rule');

            this.isLoadingRules = true;

            this.ruleStore.getList(0, 200).then((response) => {
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

            const newPriceRule = Entity.getRawEntityObject('product_price_rule');

            newPriceRule.id = utils.createId();

            newPriceRule.ruleId = null;
            newPriceRule.productId = this.product.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = this.defaultCurrency.id;

            this.product.priceRules.push(newPriceRule);
        },

        onAddCurrency(ruleId, currency) {
            const defaultCurrencyPrices = this.priceRuleGroups[ruleId].currencies[this.defaultCurrency.id].prices;

            defaultCurrencyPrices.forEach((currencyPrice) => {
                const newPriceRule = deepCopyObject(currencyPrice);

                newPriceRule.id = utils.createId();
                newPriceRule.currencyId = currency.id;

                this.product.priceRules.push(newPriceRule);
            });
        },

        onPriceGroupDelete(ruleId) {
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
                    const newPriceRule = deepCopyObject(price);
                    newPriceRule.ruleId = null;

                    this.product.priceRules.push(newPriceRule);
                });
            });
        },

        onPriceRuleDuplicate(priceRule) {
            const newPriceRule = deepCopyObject(priceRule);

            newPriceRule.id = utils.createId();

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
        },

        onQuantityEndChange(value, price, priceGroup) {
            const currencyPrices = priceGroup.currencies[price.currencyId].prices;

            if (!currencyPrices.length) {
                return;
            }

            if (currencyPrices[currencyPrices.length - 1].id === price.id && value !== null) {
                const newPriceRule = Entity.getRawEntityObject('product_price_rule');

                newPriceRule.id = utils.createId();

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
