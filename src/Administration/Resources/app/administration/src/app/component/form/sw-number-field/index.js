import template from './sw-number-field.html.twig';
import './sw-number-field.scss';

const { Component } = Shopware;
const { warn } = Shopware.Utils.debug;

/**
 * @public
 * @description Number field component which supports Int and Float with optional min, max and step.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-number-field type="number" label="Name" v-model="model" numberType="int"
 * :max="20" :min="5" :step="5"></sw-number-field>
 */
Component.extend('sw-number-field', 'sw-text-field', {
    template,
    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        numberType: {
            type: String,
            required: false,
            default: 'float',
            validValues: ['float', 'int'],
            validator(value) {
                return ['float', 'int'].includes(value);
            },
        },

        step: {
            type: Number,
            required: false,
            default: null,
        },

        min: {
            type: Number,
            required: false,
            default: null,
        },

        max: {
            type: Number,
            required: false,
            default: null,
        },

        value: {
            type: Number,
            required: false,
            default: null,
        },

        digits: {
            type: Number,
            required: false,
            default: 2,
            validator(value) {
                const isInt = value === Math.floor(value);
                if (!isInt) {
                    warn('sw-number-field', 'Provided prop digits must be of type integer');
                }
                return isInt;
            },
        },

        allowEmpty: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentValue: this.value,
        };
    },

    computed: {
        realStep() {
            if (this.step === null) {
                return this.numberType === 'int' ? 1 : 0.01;
            }

            return (this.numberType === 'int') ? Math.round(this.step) : this.step;
        },

        realMinimum() {
            if (this.min === null) {
                return null;
            }
            return (this.numberType === 'int') ? Math.ceil(this.min) : this.min;
        },

        realMaximum() {
            if (this.max === null) {
                return null;
            }

            return (this.numberType === 'int') ? Math.floor(this.max) : this.max;
        },

        stringRepresentation() {
            if (this.currentValue === null) {
                return '';
            }

            return this.currentValue.toString();
        },
    },

    watch: {
        value: {
            handler() {
                if (this.value === null || this.value === undefined) {
                    this.currentValue = null;
                    return;
                }

                this.computeValue(this.value.toString());
            },
            immediate: true,
        },
    },

    methods: {
        onChange(event) {
            this.computeValue(event.target.value);
            this.$emit('change', this.currentValue);
        },

        onInput(event) {
            let val = Number.parseFloat(event.target.value);

            if (val !== Number.NaN) {
                if (this.max && val > this.max) {
                    val = this.max;
                }
                if (this.min && val < this.min) {
                    val = this.min;
                }

                this.$emit('input-change', val);
            }
        },

        increaseNumberByStep() {
            this.computeValue((this.currentValue + this.realStep).toString());
            this.$emit('change', this.currentValue);
        },

        decreaseNumberByStep() {
            this.computeValue((this.currentValue - this.realStep).toString());
            this.$emit('change', this.currentValue);
        },

        computeValue(stringRepresentation) {
            const value = this.getNumberFromString(stringRepresentation);
            this.currentValue = this.parseValue(value);
        },

        parseValue(value) {
            if (value === null || Number.isNaN(value) || !Number.isFinite(value)) {
                if (this.allowEmpty) {
                    return null;
                }

                return this.parseValue(0);
            }

            return this.checkForInteger(this.checkBoundaries(value));
        },

        checkBoundaries(value) {
            if (this.realMaximum !== null && value > this.realMaximum) {
                value = this.realMaximum;
            }

            if (this.realMinimum !== null && value < this.realMinimum) {
                value = this.realMinimum;
            }

            return value;
        },

        getNumberFromString(value) {
            let splits = value.split('e').shift();
            splits = splits.replace(/,/g, '.').split('.');

            if (splits.length === 1) {
                return parseFloat(splits[0]);
            }

            if (this.numberType === 'int') {
                return parseInt(splits.join(''), 10);
            }

            const { decimals, transfer } = this.applyDigits(splits[splits.length - 1]);
            const integer = parseInt(splits.slice(0, splits.length - 1).join(''), 10) + transfer;

            return parseFloat(`${integer}.${decimals}`);
        },

        checkForInteger(value) {
            if (this.numberType !== 'int') {
                return value;
            }

            const floor = Math.floor(value);
            if (floor !== value) {
                this.$nextTick(() => {
                    this.$forceUpdate();
                });
            }
            return floor;
        },

        applyDigits(decimals) {
            if (decimals.length <= this.digits) {
                return {
                    decimals,
                    transfer: 0,
                };
            }

            let asString = decimals.substr(0, this.digits + 1);
            let asNumber = parseFloat(asString);
            asNumber = Math.round(asNumber / 10);
            asString = asNumber.toString();

            if (asString.length > this.digits) {
                return {
                    decimals: asString.substr(1, asString.length),
                    transfer: 1,
                };
            }

            asString = '0'.repeat(this.digits - asString.length) + asString;
            return {
                decimals: asString,
                transfer: 0,
            };
        },
    },
});
