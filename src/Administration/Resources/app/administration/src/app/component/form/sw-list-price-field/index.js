import template from './sw-list-price-field.html.twig';
import './sw-list-price-field.scss';

const { Component } = Shopware;

Component.register('sw-list-price-field', {
    template,

    inheritAttrs: false,

    props: {
        price: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },

        defaultPrice: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },

        label: {
            required: false,
            default: true
        },

        taxRate: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        currency: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        compact: {
            required: false,
            default: false
        },

        error: {
            type: Object,
            required: false,
            default: null
        },

        disabled: {
            required: false,
            default: false
        },

        enableInheritance: {
            type: Boolean,
            required: false,
            default: false
        },

        disableSuffix: {
            type: Boolean,
            required: false,
            default: false
        },

        vertical: {
            type: Boolean,
            required: false,
            default: false
        },

        hideListPrices: {
            required: false,
            default: false
        }
    },

    computed: {
        priceForCurrency: {
            get() {
                const priceForCurrency = Object.values(this.price).find((price) => {
                    return price.currencyId === this.currency.id;
                });

                // check if price exists
                if (priceForCurrency) {
                    return priceForCurrency;
                }

                // otherwise calculate values
                return {
                    gross: this.convertPrice(this.defaultPrice.gross),
                    linked: this.defaultPrice.linked,
                    net: this.convertPrice(this.defaultPrice.net),
                    listPrice: this.defaultPrice.listPrice
                };
            },
            set(newValue) {
                this.priceForCurrency.gross = newValue.gross;
                this.priceForCurrency.linked = newValue.linked;
                this.priceForCurrency.net = newValue.net;
            }
        },

        listPrice: {
            get() {
                const price = this.priceForCurrency;

                if (price.listPrice) {
                    return [price.listPrice];
                }

                return [{
                    gross: null,
                    currencyId: this.defaultPrice.currencyId ? this.defaultPrice.currencyId : this.currency.id,
                    linked: true,
                    net: null
                }];
            },

            set(newValue) {
                const price = this.priceForCurrency;

                if (price) {
                    this.$set(price, 'listPrice', newValue);
                }
            }
        },

        defaultListPrice() {
            const price = this.defaultPrice.listPrice;

            if (price) {
                return price;
            }

            return {
                currencyId: this.defaultPrice.currencyId ? this.defaultPrice.currencyId : this.currency.id,
                gross: null,
                net: null,
                linked: true
            };
        },

        isInherited() {
            const priceForCurrency = Object.values(this.price).find((price) => {
                return price.currencyId === this.currency.id;
            });

            return !priceForCurrency;
        }
    },

    methods: {
        listPriceChanged(value) {
            if (Number.isNaN(value.gross) || Number.isNaN(value.net)) {
                value = null;
            }

            this.listPrice = value;
        },

        convertPrice(value) {
            const calculatedPrice = value * this.currency.factor;
            const priceRounded = calculatedPrice.toFixed(this.currency.decimalPrecision);
            return Number(priceRounded);
        }
    }
});
