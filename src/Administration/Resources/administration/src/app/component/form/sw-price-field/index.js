import { Component } from 'src/core/shopware';
import template from './sw-price-field.html.twig';
import './sw-price-field.less';

Component.register('sw-price-field', {
    template,

    inheritAttrs: false,

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
        },
        currency: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        validation: {
            type: [String, Array, Object, Boolean],
            required: false,
            default: null
        }
    },

    data() {
        return {
            locked: true
        };
    },

    watch: {
        locked(value) {
            if (value === true) {
                this.price.net = this.convertGrossToNet(this.price.gross);
            }
        },

        taxRate() {
            // ToDo: Does not trigger value update!?
            this.price.net = this.convertGrossToNet(this.price.gross);
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
            if (!value || value === null || typeof value !== 'number') {
                return null;
            }

            return value * this.getMathTaxRate();
        },

        /**
         * Todo: We need to change this to server side calculation because of issues with floating point numbers
         */
        convertGrossToNet(value) {
            if (!value || value === null || typeof value !== 'number') {
                return null;
            }

            return value / this.getMathTaxRate();
        },

        getMathTaxRate() {
            if (!this.taxRate || !this.taxRate.rate) {
                return 1;
            }

            return (this.taxRate.rate / 100) + 1;
        }
    }
});
