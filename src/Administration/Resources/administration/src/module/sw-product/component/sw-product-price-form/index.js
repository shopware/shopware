import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

Component.register('sw-product-price-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        taxRates: {
            type: Array,
            required: true,
            default: []
        },
        currencies: {
            type: Array,
            required: true,
            default: []
        }
    },

    computed: {
        productTaxRate() {
            return this.taxRates.find((taxRate) => {
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
        priceIsCalculating(value) {
            this.$emit('calculating', value);
        }
    }
});
