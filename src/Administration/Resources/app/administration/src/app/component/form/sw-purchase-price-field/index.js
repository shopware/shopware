/**
 * @sw-package framework
 */

import template from './sw-purchase-price-field.html.twig';

/**
 * @private
 */
export default {
    template,

    emits: ['update:value'],

    props: {
        price: {
            type: Array,
            required: true,
        },

        compact: {
            type: Boolean,
            required: false,
            default: false,
        },

        taxRate: {
            type: Object,
            required: true,
        },

        error: {
            type: Object,
            required: false,
            default: null,
        },

        label: {
            required: false,
            default: true,
        },

        disabled: {
            required: false,
            default: false,
        },

        currency: {
            type: Object,
            required: true,
        },
    },

    computed: {
        purchasePrice: {
            get() {
                const priceForCurrency = this.price.find((price) => price.currencyId === this.currency.id);
                if (priceForCurrency) {
                    return [priceForCurrency];
                }

                return [
                    {
                        currencyId: this.currency.id,
                        linked: true,
                        gross: null,
                        net: null,
                    },
                ];
            },

            set(newPurchasePrice) {
                if (newPurchasePrice.gross === null && newPurchasePrice.net === null) {
                    this.$emit('update:value', null);

                    return;
                }

                const prices = [...this.price];
                const index = prices.findIndex((price) => price.currencyId === newPurchasePrice.currencyId);

                if (index !== -1) {
                    prices.splice(index, 1, newPurchasePrice);
                } else {
                    prices.push(newPurchasePrice);
                }

                this.$emit('update:value', prices);
            },
        },
    },

    methods: {
        purchasePriceChanged(value) {
            this.purchasePrice = value;
        },
    },
};
