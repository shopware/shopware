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
        purchasePrice() {
            const purchasePrice = this.price.find(({ currencyId }) => currencyId === this.currency.id) ?? {
                currencyId: this.currency.id,
                linked: true,
                gross: null,
                net: null,
            };

            return [purchasePrice];
        },
    },

    methods: {
        purchasePriceChanged(newPurchasePrice) {
            const prices = [...this.price];
            const index = prices.findIndex((price) => price.currencyId === newPurchasePrice.currencyId);

            if (newPurchasePrice.gross === null && newPurchasePrice.net === null) {
                if (index !== -1) {
                    prices.splice(index, 1);
                }

                this.$emit('update:value', prices.length ? prices : null);

                return;
            }

            if (index !== -1) {
                prices.splice(index, 1, newPurchasePrice);
            } else {
                prices.push(newPurchasePrice);
            }

            this.$emit('update:value', prices);
        },
    },
};
