import { Component } from 'src/core/shopware';
import template from './sw-price-field.html.twig';
import './sw-price-field.less';

Component.register('sw-price-field', {
    template,

    props: {
        price: {
            type: Object,
            required: true,
            default() {
                return {
                    net: null,
                    gross: null
                };
            }
        },
        taxRate: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            locked: true
        };
    },

    computed: {
        mathematicalTaxRate() {
            return (this.taxRate.rate / 100) + 1;
        }
    },

    watch: {
        locked(value) {
            if (value === true) {
                this.price.net = this.convertGrossToNet(this.price.gross);
            }
        }
    },

    methods: {
        onLockSwitch() {
            this.locked = !this.locked;
        },

        onPriceGrossChange(value) {
            this.$emit('priceGrossChange', value);
            this.$emit('change', this.price);

            if (this.locked) {
                this.price.net = this.convertGrossToNet(value);
            }
        },

        onPriceNetChange(value) {
            this.$emit('priceNetChange', value);
            this.$emit('change', this.price);

            if (this.locked) {
                this.price.gross = this.convertNetToGross(value);
            }
        },

        /**
         * Todo: We need to change this to server side calculation because of issues with floating point numbers
         */
        convertNetToGross(value) {
            return value * this.mathematicalTaxRate;
        },

        /**
         * Todo: We need to change this to server side calculation because of issues with floating point numbers
         */
        convertGrossToNet(value) {
            return value / this.mathematicalTaxRate;
        }
    }
});
