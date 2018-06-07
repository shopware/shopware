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
            contextRules: [],
            totalContextRules: 0,
            isLoadingContextRules: false
        };
    },

    computed: {
        priceRuleGroups() {
            const priceRuleGroups = {};

            this.product.priceRules.forEach((ctx) => {
                if (!priceRuleGroups[ctx.contextRuleId]) {
                    priceRuleGroups[ctx.contextRuleId] = {
                        contextRuleId: ctx.contextRuleId,
                        contextRule: this.findContextRuleById(ctx.contextRuleId),
                        currencies: {}
                    };
                }

                if (!priceRuleGroups[ctx.contextRuleId].currencies[ctx.currencyId]) {
                    priceRuleGroups[ctx.contextRuleId].currencies[ctx.currencyId] = {
                        currencyId: ctx.currencyId,
                        currency: this.findCurrencyById(ctx.currencyId),
                        prices: []
                    };
                }

                priceRuleGroups[ctx.contextRuleId].currencies[ctx.currencyId].prices.push(ctx);
            });

            return priceRuleGroups;
        },

        isLoaded() {
            return !this.isLoading &&
                   !this.isLoadingContextRules &&
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
            this.contextRuleStore = Shopware.State.getStore('context_rule');

            this.isLoadingContextRules = true;

            this.contextRuleStore.getList(0, 200).then((response) => {
                this.contextRules = response.items;
                this.totalContextRules = response.total;

                this.isLoadingContextRules = false;
            });
        },

        onContextRuleChange(value, contextRuleId) {
            this.product.priceRules.forEach((priceRule) => {
                if (priceRule.contextRuleId === contextRuleId) {
                    priceRule.contextRuleId = value;
                }
            });
        },

        onAddNewPriceGroup() {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            const newPriceRule = Entity.getRawEntityObject('product_price_rule');

            newPriceRule.id = utils.createId();

            newPriceRule.contextRuleId = null;
            newPriceRule.productId = this.product.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = this.defaultCurrency.id;

            this.product.priceRules.push(newPriceRule);
        },

        onAddCurrency(contextRuleId, currency) {
            const defaultCurrencyPrices = this.priceRuleGroups[contextRuleId].currencies[this.defaultCurrency.id].prices;

            defaultCurrencyPrices.forEach((currencyPrice) => {
                const newPriceRule = deepCopyObject(currencyPrice);

                newPriceRule.id = utils.createId();
                newPriceRule.currencyId = currency.id;

                this.product.priceRules.push(newPriceRule);
            });
        },

        onPriceGroupDelete(contextRuleId) {
            this.product.priceRules = this.product.priceRules.filter((priceRule) => {
                return priceRule.contextRuleId !== contextRuleId;
            });
        },

        onPriceGroupDuplicate(priceGroup) {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            Object.keys(priceGroup.currencies).forEach((currencyId) => {
                priceGroup.currencies[currencyId].prices.forEach((price) => {
                    const newPriceRule = deepCopyObject(price);
                    newPriceRule.contextRuleId = null;

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
                const priceRuleGroup = this.priceRuleGroups[priceRule.contextRuleId];
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
                newPriceRule.contextRuleId = priceGroup.contextRuleId;
                newPriceRule.quantityStart = price.quantityEnd + 1;
                newPriceRule.quantityEnd = null;
                newPriceRule.currencyId = price.currencyId;

                this.product.priceRules.push(newPriceRule);
            }
        },

        findContextRuleById(contextRuleId) {
            return this.contextRules.find((rule) => {
                return rule.id === contextRuleId;
            });
        },

        findCurrencyById(currencyId) {
            return this.currencies.find((currency) => {
                return currency.id === currencyId;
            });
        }
    }
});
