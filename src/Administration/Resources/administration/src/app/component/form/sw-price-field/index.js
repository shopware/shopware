import template from './sw-price-field.html.twig';
import './sw-price-field.less';

/**
 * @public
 * @status ready
 * @example-type static
 * @component-example
 * <sw-price-field :price="{ net: 10, gross: 11.90 }" :currency="{ symbol: '€' }"></sw-price-field>
 */
export default {
    name: 'sw-price-field',
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
            required: false,
            default: null
        }
    },

    watch: {
        'price.linked': function priceLinkedWatcher(value) {
            if (value === true) {
                this.price.net = this.convertGrossToNet(this.price.gross);
            }
        },

        'taxRate.taxRate': function taxRateWatcher() {
            if (this.price.linked === true) {
                this.price.net = this.convertGrossToNet(this.price.gross);
            }
        }
    },

    methods: {
        onLockSwitch() {
            this.price.linked = !this.price.linked;
            this.$emit('priceLockChange', this.price.linked);
            this.$emit('change', this.price);
        },

        onPriceGrossChange(value) {
            this.$emit('priceGrossChange', value);
            this.$emit('change', this.price);

            if (this.price.linked) {
                this.price.net = this.convertGrossToNet(value);
            }
        },

        onPriceNetChange(value) {
            this.$emit('priceNetChange', value);
            this.$emit('change', this.price);

            if (this.price.linked) {
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
            if (!this.taxRate || !this.taxRate.taxRate) {
                return 1;
            }

            return (this.taxRate.taxRate / 100) + 1;
        }
    }
};
