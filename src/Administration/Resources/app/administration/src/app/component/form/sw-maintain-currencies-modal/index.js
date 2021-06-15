import template from './sw-maintain-currencies-modal.html.twig';
import './sw-maintain-currencies-modal.scss';

const { Component } = Shopware;

Component.register('sw-maintain-currencies-modal', {
    template,

    props: {
        currencies: {
            type: Array,
            required: true,
        },

        prices: {
            type: Array,
            required: true,
        },

        defaultPrice: {
            type: Object,
            required: true,
        },

        taxRate: {
            type: Object,
            required: true,
        },

        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        hideListPrices: {
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            clonePrices: null,
        };
    },

    computed: {
        maintainCurrencyColumns() {
            return [
                {
                    property: 'name',
                    label: '',
                    visible: true,
                    allowResize: false,
                    primary: true,
                    rawData: false,
                    width: '150px',
                }, {
                    property: 'price',
                    label: 'sw-maintain-currencies-modal.columnPrice',
                    visible: true,
                    allowResize: false,
                    primary: true,
                    rawData: false,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.sortCurrencies();
            this.clonePrices = Shopware.Utils.object.cloneDeep(this.prices);
        },

        sortCurrencies() {
            this.currencies.sort((a, b) => {
                if (a.isSystemDefault) {
                    return -1;
                }
                if (b.isSystemDefault) {
                    return +1;
                }
                if (a.translated.name < b.translated.name) {
                    return -1;
                }
                if (a.translated.name > b.translated.name) {
                    return 1;
                }
                return 0;
            });
        },

        convertPrice(value, currency) {
            const calculatedPrice = value * currency.factor;
            const priceRounded = calculatedPrice.toFixed(currency.decimalPrecision);

            return Number(priceRounded);
        },

        isCurrencyInherited(currency) {
            const priceForCurrency = this.prices.find((price) => {
                return price.currencyId === currency.id;
            });

            return !priceForCurrency;
        },

        onInheritanceRestore(currencyId) {
            // create entry for currency in prices
            const indexOfPrice = this.prices.findIndex((price) => {
                return price.currencyId === currencyId;
            });

            this.$delete(this.prices, indexOfPrice);
        },

        onInheritanceRemove(currency) {
            const price = {
                currencyId: currency.id,
                gross: this.convertPrice(this.defaultPrice.gross, currency),
                linked: this.defaultPrice.linked,
                net: this.convertPrice(this.defaultPrice.net, currency),
                listPrice: null,
            };

            if (this.defaultPrice.listPrice) {
                price.listPrice = {
                    currencyId: currency.id,
                    gross: this.convertPrice(this.defaultPrice.listPrice.gross, currency),
                    linked: this.defaultPrice.listPrice.linked,
                    net: this.convertPrice(this.defaultPrice.listPrice.net, currency),
                };
            }

            // create new entry for currency in prices
            this.$set(this.prices, this.prices.length, price);
        },

        onCancel() {
            this.$emit('modal-close', this.clonePrices);
        },

        onApply() {
            this.$emit('modal-close', this.prices);
        },
    },
});
