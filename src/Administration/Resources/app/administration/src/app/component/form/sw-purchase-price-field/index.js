import template from './sw-purchase-price-field.html.twig';

const { Component } = Shopware;

Component.register('sw-purchase-price-field', {
    template,
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

        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        label: {
            required: false,
            default: true,
        },

        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
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

                return [{
                    gross: null,
                    currencyId: this.currency.id,
                    linked: true,
                    net: null,
                }];
            },

            set(newPurchasePrice) {
                let priceForCurrency = this.price.find((price) => price.currencyId === newPurchasePrice.currencyId);
                if (priceForCurrency) {
                    priceForCurrency = newPurchasePrice;
                } else {
                    this.price.push(newPurchasePrice);
                }

                this.$emit('input', this.price);
            },
        },
    },

    methods: {
        purchasePriceChanged(value) {
            this.purchasePrice = value;
        },
    },
});
