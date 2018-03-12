import { Component, Mixin } from 'src/core/shopware';
import template from './sw-field.html.twig';
import './sw-field.less';

Component.register('sw-field', {
    template,

    mixins: [
        Mixin.getByName('validation')
    ],

    props: {
        type: {
            type: String,
            required: false,
            default: 'text'
        },
        name: {
            type: String,
            required: false
        },
        label: {
            type: String,
            required: false,
            default: ''
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        suffix: {
            type: String,
            required: false,
            default: ''
        },
        value: {
            type: [String, Boolean, Number, Date],
            required: false,
            default: null
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        error: {
            type: Object,
            required: false,
            default: null
        },
        options: {
            type: Array,
            required: false,
            default: () => {
                return [];
            }
        }
    },

    data() {
        return {
            currentValue: null,
            valueType: 'string'
        };
    },

    computed: {
        hasError() {
            return this.error !== null && typeof this.error !== 'undefined' && Object.keys(this.error).length > 0;
        },

        hasErrorCls() {
            return !this.isValid || this.hasError;
        }
    },

    watch: {
        value() {
            this.currentValue = this.value;
        }
    },

    mounted() {
        this.valueType = typeof this.value;
        this.currentValue = this.value;
    },

    methods: {
        onInput(event) {
            this.currentValue = this.getValueFromEvent(event);

            this.$emit('input', this.currentValue);
        },

        onChange(event) {
            this.currentValue = this.getValueFromEvent(event);

            this.$emit('change', this.currentValue);

            if (['checkbox', 'radio', 'switch'].includes(this.type)) {
                this.$emit('input', this.currentValue);
            }
        },

        getValueFromEvent(event) {
            let value = event.target.value;

            if (event.target.type === 'checkbox') {
                value = event.target.checked;
            }

            return this.convertValueType(value);
        },

        convertValueType(value) {
            if (this.valueType === 'number') {
                return parseFloat(value);
            }

            if (this.valueType === 'boolean') {
                return value === 'true' || value === true;
            }

            if (this.valueType === 'string' && value.length <= 0) {
                return null;
            }

            return value;
        }
    }
});
