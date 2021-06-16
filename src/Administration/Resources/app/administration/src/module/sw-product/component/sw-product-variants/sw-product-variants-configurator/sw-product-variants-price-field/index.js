import template from './sw-product-variants-price-field.html.twig';
import './sw-product-variants-price-field.scss';

const { Component, Application } = Shopware;
const utils = Shopware.Utils;

Component.register('sw-product-variants-price-field', {
    template,

    props: {
        price: {
            type: Object,
            required: true,
        },

        taxRate: {
            type: String,
            required: false,
            default: null,
        },

        currency: {
            type: Object,
            required: true,
        },

        readonly: {
            type: Boolean,
            required: false,
            default: false,
        },

        onlyPositive: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        calculatePriceApiService() {
            return Application.getContainer('factory').apiService.getByName('calculate-price');
        },
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
        },
    },

    methods: {
        onLockSwitch() {
            if (this.readonly) {
                return false;
            }
            this.price.linked = !this.price.linked;
            this.$emit('price-lock-change', this.price.linked);
            this.$emit('change', this.price);
            return true;
        },

        onPriceGrossChange(value) {
            this.price.gross = value;

            if (this.price.linked) {
                this.$emit('price-calculate', true);
                this.onPriceGrossChangeDebounce(Number(this.price.gross));
            }
        },

        onPriceGrossChangeDebounce: utils.debounce(function onPriceGrossChange(value) {
            this.$emit('price-gross-change', value);
            this.$emit('change', this.price);

            this.convertGrossToNet(value);
        }, 500),

        onPriceNetChange(value) {
            this.price.net = value;

            if (this.price.linked) {
                this.$emit('price-calculate', true);
                this.onPriceNetChangeDebounce(Number(this.price.net));
            }
        },

        onPriceNetChangeDebounce: utils.debounce(function onPriceNetChange(value) {
            this.$emit('price-net-change', value);
            this.$emit('change', this.price);

            this.convertNetToGross(value);
        }, 500),

        convertNetToGross(value) {
            if (!value || typeof value !== 'number') {
                return false;
            }
            this.$emit('price-calculate', true);

            this.requestTaxValue(value, 'net').then((res) => {
                this.price.gross = Number(this.price.net) + res;
            });
            return true;
        },

        convertGrossToNet(value) {
            if (!value || typeof value !== 'number') {
                return false;
            }
            this.$emit('price-calculate', true);

            this.requestTaxValue(value, 'gross').then((res) => {
                this.price.net = Number(this.price.gross) - res;
            });
            return true;
        },

        requestTaxValue(value, outputType) {
            this.$emit('price-calculate', true);

            return new Promise((resolve) => {
                if (!value || typeof value !== 'number' || !this.price[outputType] || !this.taxRate || !outputType) {
                    return null;
                }

                this.calculatePriceApiService.calculatePrice({
                    taxId: this.taxRate,
                    price: this.price[outputType],
                    output: outputType,
                    currencyId: this.currency.id,
                }).then(({ data }) => {
                    resolve(data.calculatedTaxes[0].tax);
                    this.$emit('price-calculate', false);
                });
                return true;
            });
        },
    },
});
