import { Component, Mixin } from 'src/core/shopware';
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
        }
    },

    computed: {
        contextPriceGroups() {
            const contextPriceGroups = {};

            this.product.contextPrices.sort((a, b) => {
                if (a.quantityStart < b.quantityStart) {
                    return -1;
                }

                if (a.quantityStart > b.quantityStart) {
                    return 1;
                }

                return 0;
            });

            this.product.contextPrices.forEach((ctx) => {
                if (!contextPriceGroups[ctx.contextRuleId]) {
                    contextPriceGroups[ctx.contextRuleId] = {
                        contextRuleId: ctx.contextRuleId,
                        contextPrices: []
                    };
                }

                contextPriceGroups[ctx.contextRuleId].contextPrices.push(ctx);
            });

            return contextPriceGroups;
        },

        productTaxRate() {
            return this.taxes.find((taxRate) => {
                return taxRate.id === this.product.taxId;
            });
        }
    },

    methods: {
        onContextRuleChange(value, priceGroup) {
            priceGroup.contextPrices.forEach((contextPrice) => {
                contextPrice.contextRuleId = value;
            });
        }
    }
});
