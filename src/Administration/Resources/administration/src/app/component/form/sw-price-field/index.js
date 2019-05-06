import { Application } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-price-field.html.twig';
import './sw-price-field.scss';

/**
 * @public
 * @status ready
 * @example-type static
 * @component-example
 * <sw-price-field :taxRate="{ taxRate: 19 }"
 *                 :price="{ net: 10, gross: 11.90 }"
 *                 :currency="{ symbol: '€' }">
 * </sw-price-field>
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
        },
        label: {
            required: false,
            default: true
        },
        compact: {
            required: false,
            default: false
        }
    },

    watch: {
        'price.linked': function priceLinkedWatcher(value) {
            if (value === true) {
                this.convertGrossToNet(this.price.gross);
            }
        },

        'taxRate.taxRate': function taxRateWatcher() {
            if (this.price.linked === true) {
                this.convertGrossToNet(this.price.gross);
            }
        }
    },

    computed: {
        calculatePriceApiService() {
            return Application.getContainer('factory').apiService.getByName('calculate-price');
        }
    },

    methods: {
        onLockSwitch() {
            this.price.linked = !this.price.linked;
            this.$emit('priceLockChange', this.price.linked);
            this.$emit('change', this.price);
        },

        onPriceGrossChange(value) {
            this.price.gross = value;

            if (this.price.linked) {
                this.$emit('calculating', true);
                this.onPriceGrossChangeDebounce(value);
            }
        },

        onPriceGrossChangeDebounce: utils.debounce(function onPriceGrossChange(value) {
            this.$emit('priceGrossChange', value);
            this.$emit('change', this.price);

            this.convertGrossToNet(value);
        }, 500),

        onPriceNetChange(value) {
            this.price.net = value;

            if (this.price.linked) {
                this.$emit('calculating', true);
                this.onPriceNetChangeDebounce(value);
            }
        },

        onPriceNetChangeDebounce: utils.debounce(function onPriceNetChange(value) {
            this.$emit('priceNetChange', value);
            this.$emit('change', this.price);

            this.convertNetToGross(value);
        }, 500),

        convertNetToGross(value) {
            if (!value || typeof value !== 'number') {
                return false;
            }
            this.$emit('calculating', true);

            this.requestTaxValue(value, 'net').then((res) => {
                this.price.gross = this.price.net + res;
            });
            return true;
        },

        convertGrossToNet(value) {
            if (!value || typeof value !== 'number') {
                return false;
            }
            this.$emit('calculating', true);

            this.requestTaxValue(value, 'gross').then((res) => {
                this.price.net = this.price.gross - res;
            });
            return true;
        },

        requestTaxValue(value, outputType) {
            this.$emit('calculating', true);
            return new Promise((resolve) => {
                if (!value || typeof value !== 'number' || !this.price[outputType] || !this.taxRate.id || !outputType) {
                    return null;
                }

                this.calculatePriceApiService.calculatePrice({
                    taxId: this.taxRate.id,
                    currencyId: this.currency.id,
                    price: this.price[outputType],
                    output: outputType
                }).then(({ data }) => {
                    resolve(data.calculatedTaxes[0].tax);
                    this.$emit('calculating', false);
                });
                return true;
            });
        }
    }
};
