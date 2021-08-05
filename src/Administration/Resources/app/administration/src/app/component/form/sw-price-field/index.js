import template from './sw-price-field.html.twig';
import './sw-price-field.scss';

const { Component, Application } = Shopware;

/**
 * @public
 * @status ready
 * @example-type static
 * @component-example
 * <sw-price-field :taxRate="{ taxRate: 19 }"
 *                 :price="[{ net: 10, gross: 11.90, currencyId: '...' }, ...]"
 *                 :defaultPrice="{...}"
 *                 :currency="{...}">
 * </sw-price-field>
 */
Component.register('sw-price-field', {
    template,

    inheritAttrs: false,

    props: {
        price: {
            type: Array,
            required: true,
            default() {
                return [];
            },
        },

        defaultPrice: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },

        taxRate: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },

        currency: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },

        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        validation: {
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
        compact: {
            required: false,
            default: false,
        },

        error: {
            type: Object,
            required: false,
            default: null,
        },

        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        disabled: {
            required: false,
            default: false,
        },

        disableSuffix: {
            type: Boolean,
            required: false,
            default: false,
        },

        grossLabel: {
            type: String,
            required: false,
            default: null,
        },

        netLabel: {
            type: String,
            required: false,
            default: null,
        },

        name: {
            type: String,
            required: false,
            default: null,
        },

        allowEmpty: {
            type: Boolean,
            required: false,
            default: false,
        },

        inherited: {
            type: Boolean,
            required: false,
            default: undefined,
        },

        grossHelpText: {
            type: String,
            required: false,
            default: null,
        },

        netHelpText: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        calculatePriceApiService() {
            return Application.getContainer('factory').apiService.getByName('calculate-price');
        },

        priceForCurrency: {
            get() {
                const priceForCurrency = Object.values(this.price).find((price) => {
                    return price.currencyId === this.currency?.id;
                });

                // check if price exists
                if (priceForCurrency) {
                    return priceForCurrency;
                }

                // Calculate values if inherited
                if (this.isInherited) {
                    return {
                        currencyId: this.currency.id,
                        gross: this.defaultPrice.gross ? this.convertPrice(this.defaultPrice.gross) : null,
                        linked: this.defaultPrice.linked,
                        net: this.defaultPrice.net ? this.convertPrice(this.defaultPrice.net) : null,
                    };
                }

                return {
                    currencyId: this.currency.id,
                    gross: null,
                    linked: this.defaultPrice.linked,
                    net: null,
                };
            },
            set(newValue) {
                this.priceForCurrency.gross = newValue.gross;
                this.priceForCurrency.linked = newValue.linked;
                this.priceForCurrency.net = newValue.net;
            },
        },

        isInherited() {
            if (this.inherited !== undefined) {
                return this.inherited;
            }

            const priceForCurrency = Object.values(this.price).find((price) => {
                return price.currencyId === this.currency.id;
            });

            return !priceForCurrency;
        },

        isDisabled() {
            return this.isInherited || this.disabled;
        },

        labelGross() {
            const label = this.grossLabel ? this.grossLabel : this.$tc('global.sw-price-field.labelPriceGross');
            return this.label ? label : '';
        },

        labelNet() {
            const label = this.netLabel ? this.netLabel : this.$tc('global.sw-price-field.labelPriceNet');
            return this.label ? label : '';
        },

        grossError() {
            return this.error ? this.error.gross : null;
        },

        netError() {
            return this.error ? this.error.net : null;
        },

        grossFieldName() {
            return this.name ? `${this.name}-gross` : 'sw-price-field-gross';
        },

        netFieldName() {
            return this.name ? `${this.name}-net` : 'sw-price-field-net';
        },
    },

    watch: {
        'priceForCurrency.linked': function priceLinkedWatcher(value) {
            if (value === true && this.priceForCurrency.gross !== null) {
                this.convertGrossToNet(this.priceForCurrency.gross);
            }
        },

        'taxRate.id': function taxRateWatcher() {
            if (this.priceForCurrency.linked === true && this.priceForCurrency.gross !== null) {
                this.convertGrossToNet(this.priceForCurrency.gross);
            }
        },
    },

    methods: {
        onLockSwitch() {
            if (this.isDisabled) {
                return;
            }
            this.priceForCurrency.linked = !this.priceForCurrency.linked;
            this.$emit('price-lock-change', this.priceForCurrency.linked);
            this.$emit('change', this.priceForCurrency);
        },

        onPriceGrossChange(value) {
            if (this.priceForCurrency.linked) {
                this.$emit('price-calculate', true);
                this.$emit('price-gross-change', value);
                this.$emit('change', this.priceForCurrency);

                this.convertGrossToNet(value);
            }
        },

        onPriceNetChange(value) {
            if (this.priceForCurrency.linked) {
                this.$emit('price-calculate', true);
                this.$emit('price-net-change', value);
                this.$emit('change', this.priceForCurrency);

                this.convertNetToGross(value);
            }
        },

        convertNetToGross(value) {
            if (Number.isNaN(value)) {
                this.priceForCurrency.gross = this.allowEmpty ? null : 0;
                return false;
            }

            if (!value) {
                this.priceForCurrency.gross = 0;
                return false;
            }
            this.$emit('price-calculate', true);

            this.requestTaxValue(value, 'net').then((res) => {
                const newValue = this.priceForCurrency.net + res;
                this.priceForCurrency.gross = parseFloat(newValue.toPrecision(14));
            });
            return true;
        },

        convertGrossToNet(value) {
            if (Number.isNaN(value)) {
                this.priceForCurrency.net = this.allowEmpty ? null : 0;
                this.$emit('calculating', false);
                return false;
            }

            if (!value) {
                this.priceForCurrency.net = 0;
                this.$emit('calculating', false);
                return false;
            }
            this.$emit('price-calculate', true);

            this.requestTaxValue(value, 'gross').then((res) => {
                const newValue = this.priceForCurrency.gross - res;
                this.priceForCurrency.net = parseFloat(newValue.toPrecision(14));
            });
            return true;
        },

        requestTaxValue(value, outputType) {
            this.$emit('price-calculate', true);
            return new Promise((resolve) => {
                if (
                    !value ||
                    typeof value !== 'number' ||
                    !this.priceForCurrency[outputType] ||
                    !this.taxRate.id ||
                    !outputType
                ) {
                    return null;
                }

                this.calculatePriceApiService.calculatePrice({
                    taxId: this.taxRate.id,
                    currencyId: this.currency.id,
                    price: this.priceForCurrency[outputType],
                    output: outputType,
                }).then(({ data }) => {
                    let tax = 0;

                    data.calculatedTaxes.forEach((item) => {
                        tax += item.tax;
                    });

                    resolve(tax);
                    this.$emit('price-calculate', false);
                });
                return true;
            });
        },

        convertPrice(value) {
            return value * this.currency.factor;
        },

        keymonitor(event) {
            if (event.key === ',') {
                const value = event.target.value;
                event.target.value = value.replace(/,/, '.');
            }
        },
    },
});
