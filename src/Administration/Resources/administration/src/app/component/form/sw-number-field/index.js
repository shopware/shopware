import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-number-field.html.twig';

/**
 * @public
 * @description Number field component which supports Int and Float with optional min, max and step.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-number-field type="number" label="Name" v-model="model" numberType="int"
 * :max="20" :min="5" :step="5"></sw-number-field>
 */
export default {
    name: 'sw-number-field',
    extendsFrom: 'sw-text-field',
    template,

    props: {
        value: {
            type: Number,
            required: false
        },
        suffix: {
            type: String,
            required: false,
            default: ''
        },
        numberType: {
            type: String,
            required: false,
            default: 'float',
            validValues: ['float', 'int'],
            validator(value) {
                return ['float', 'int'].includes(value);
            }
        },
        step: {
            type: Number,
            required: false
        },
        min: {
            type: Number,
            required: false,
            default: null
        },
        max: {
            type: Number,
            required: false
        }
    },
    computed: {
        hasSuffix() {
            return this.suffix.length || !!this.$slots.suffix;
        },

        fieldClasses() {
            return [
                'sw-field--number',
                {
                    'has--error': !!this.hasErrorCls,
                    'has--suffix': this.hasSuffix,
                    'is--disabled': !!this.$props.disabled
                }];
        }
    },

    data() {
        return {
            currentValue: 0
        };
    },

    watch: {
        value(value) {
            this.currentValue = value;
        }
    },

    created() {
        this.currentStep = this.step;
        if (this.numberType === 'int' && !this.currentStep) {
            this.currentStep = 1;
        }
        if (this.numberType === 'float' && !this.currentStep) {
            this.currentStep = 0.01;
        }
        if (this.min > this.max) {
            warn('Min can not be higher than Max', this.$options.parent.$vnode);
        }
        if (this.max && (this.max % this.currentStep) !== 0) {
            warn('Max is not reachable via Steps', this.$options.parent.$vnode);
        }
        if (!Number.isInteger(this.currentStep) && this.numberType === 'int') {
            warn('Steps must be Integer when numberType is set as int', this.$options.parent.$vnode);
        }
    },

    methods: {
        parseValue(value) {
            if (value) {
                switch (this.numberType) {
                case 'int':
                    return parseInt(value, 10);
                case 'float':
                    value = String(value);
                    value = value.replace(/[,]/g, '.');
                    return parseFloat(value);
                default:
                    return value;
                }
            }
            return null;
        },

        onInput() {
            let value = String(this.currentValue);

            switch (this.numberType) {
            case 'int':
                value = value.replace(/[^0-9]/g, '');
                break;
            case 'float':
            default:
                value = value.replace(/[^0-9.,]+/, '');
                break;
            }

            value = value.replace(/^(-)?0+(?=\d)/, '$1');

            if (value !== '') {
                if (value <= this.max && value >= this.min) {
                    this.currentValue = value;
                } else if (value < this.min) {
                    this.currentValue = this.min;
                } else if (value > this.max) {
                    this.currentValue = this.max;
                } else {
                    this.currentValue = value;
                }
            } else {
                this.currentValue = value;
            }

            this.$emit('input', this.parseValue(this.currentValue));

            if (this.hasError) {
                this.errorStore.deleteError(this.formError);
            }
        },

        increaseNumberByStep() {
            const value = this.currentValue;

            if (this.max) {
                if (value + this.currentStep <= this.max) {
                    this.currentValue = Math.round((value + this.currentStep) * 100) / 100;
                }
            } else {
                this.currentValue = Math.round((value + this.currentStep) * 100) / 100;
            }

            this.$emit('input', this.currentValue);
        },

        decreaseNumberByStep() {
            const value = this.currentValue;

            if (this.min === null || value - this.currentStep >= this.min) {
                this.currentValue = Math.round((value - this.currentStep) * 100) / 100;
                this.$emit('input', this.currentValue);
            }
        }
    }
};
