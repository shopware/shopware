import { Component } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import template from './sw-product-maintain-currencies-modal.html.twig';
import './sw-product-maintain-currencies-modal.scss';

Component.register('sw-product-maintain-currencies-modal', {
    template,

    props: {
        currencies: {
            type: Array,
            required: true
        },

        product: {
            type: Object,
            required: true
        },

        defaultPrice: {
            type: Object,
            required: true
        },

        productTaxRate: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            prices: null
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
                    width: '150px'
                }, {
                    property: 'price',
                    label: this.$tc('sw-product.maintainCurrenciesModal.columnPrice'),
                    visible: true,
                    allowResize: false,
                    primary: true,
                    rawData: false
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.prices = deepCopyObject(this.product.price);
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
            // create new entry for currency in prices
            this.$set(this.prices, this.prices.length, {
                currencyId: currency.id,
                gross: this.convertPrice(this.defaultPrice.gross, currency),
                linked: this.defaultPrice.linked,
                net: this.convertPrice(this.defaultPrice.net, currency)
            });
        },

        onCancel() {
            this.$emit('modal-close', {
                action: 'cancel',
                changeSet: null
            });
        },

        onApply() {
            const price = this.prices;

            this.$emit('modal-close', {
                action: 'apply',
                changeSet: { price }
            });
        }
    }
});
