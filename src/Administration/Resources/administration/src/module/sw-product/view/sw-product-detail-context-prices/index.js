import { Component, Mixin } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import utils from 'src/core/service/util.service';
import template from './sw-product-detail-context-prices.html.twig';
import './sw-product-detail-context-prices.less';

Component.register('sw-product-detail-context-prices', {
    template,

    mixins: [
        Mixin.getByName('contextRuleList')
    ],

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

    computed: {
        contextPriceGroups() {
            const contextPriceGroups = {};

            this.product.contextPrices.forEach((ctx) => {
                if (!contextPriceGroups[ctx.contextRuleId]) {
                    contextPriceGroups[ctx.contextRuleId] = {
                        contextRuleId: ctx.contextRuleId,
                        contextRule: this.findContextRuleById(ctx.contextRuleId),
                        currencies: {}
                    };
                }

                if (!contextPriceGroups[ctx.contextRuleId].currencies[ctx.currencyId]) {
                    contextPriceGroups[ctx.contextRuleId].currencies[ctx.currencyId] = {
                        currencyId: ctx.currencyId,
                        currency: this.findCurrencyById(ctx.currencyId),
                        prices: []
                    };
                }

                contextPriceGroups[ctx.contextRuleId].currencies[ctx.currencyId].prices.push(ctx);
            });

            return contextPriceGroups;
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

    methods: {
        onContextRuleChange(value, contextRuleId) {
            this.product.contextPrices.forEach((contextPrice) => {
                if (contextPrice.contextRuleId === contextRuleId) {
                    contextPrice.contextRuleId = value;
                }
            });
        },

        onAddNewPriceGroup() {
            const newContextPrice = Shopware.Entity.getRawEntityObject('product_context_price');

            newContextPrice.id = utils.createId();

            newContextPrice.contextRuleId = null;
            newContextPrice.productId = this.product.id;
            newContextPrice.quantityStart = 1;
            newContextPrice.quantityEnd = null;
            newContextPrice.currencyId = this.defaultCurrency.id;

            this.product.contextPrices.push(newContextPrice);
        },

        onAddCurrency(contextRuleId, currency) {
            const defaultCurrencyPrices = this.contextPriceGroups[contextRuleId].currencies[this.defaultCurrency.id].prices;

            defaultCurrencyPrices.forEach((currencyPrice) => {
                const newContextPrice = deepCopyObject(currencyPrice);

                newContextPrice.id = utils.createId();
                newContextPrice.currencyId = currency.id;

                this.product.contextPrices.push(newContextPrice);
            });
        },

        onPriceGroupDelete(contextRuleId) {
            this.product.contextPrices = this.product.contextPrices.filter((contextPrice) => {
                return contextPrice.contextRuleId !== contextRuleId;
            });
        },

        onPriceGroupDuplicate(priceGroup) {
            Object.keys(priceGroup.currencies).forEach((currencyId) => {
                priceGroup.currencies[currencyId].prices.forEach((price) => {
                    const newContextPrice = deepCopyObject(price);
                    newContextPrice.contextRuleId = null;

                    this.product.contextPrices.push(newContextPrice);
                });
            });
        },

        onPriceRuleDuplicate(contextPrice) {
            const newContextPrice = deepCopyObject(contextPrice);

            newContextPrice.id = utils.createId();

            this.product.contextPrices.push(newContextPrice);
        },

        onPriceRuleDelete(contextPrice) {
            this.product.contextPrices = this.product.contextPrices.filter((price) => {
                return price.id !== contextPrice.id;
            });
        },

        onQuantityEndChange(value, price, priceGroup) {
            const currencyPrices = priceGroup.currencies[price.currencyId].prices;

            if (!currencyPrices.length) {
                return;
            }

            if (currencyPrices[currencyPrices.length - 1].id === price.id && value !== null) {
                const newContextPrice = Shopware.Entity.getRawEntityObject('product_context_price');

                newContextPrice.id = utils.createId();

                newContextPrice.productId = this.product.id;
                newContextPrice.contextRuleId = priceGroup.contextRuleId;
                newContextPrice.quantityStart = price.quantityEnd + 1;
                newContextPrice.quantityEnd = null;
                newContextPrice.currencyId = price.currencyId;

                this.product.contextPrices.push(newContextPrice);
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
