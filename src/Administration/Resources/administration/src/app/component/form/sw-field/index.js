import { Component, Mixin } from 'src/core/shopware';
import template from './sw-field.html.twig';
import './sw-field.less';

Component.register('sw-field', {
    template,

    mixins: [
        Mixin.getByName('validation')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

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
        errorMessage: {
            type: String,
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
            valueType: 'string',
            boundExpressionPath: []
        };
    },

    watch: {
        value() {
            this.currentValue = this.value;
        }
    },

    mounted() {
        this.valueType = typeof this.value;
        this.currentValue = this.value;

        if (this.$vnode.data && this.$vnode.data.model) {
            this.boundExpressionPath = this.$vnode.data.model.expression.split('.');
        }
    },

    methods: {
        onInput(event) {
            this.currentValue = this.getValueFromEvent(event);

            this.$emit('input', this.currentValue);

            if (this.hasError()) {
                this.$store.commit('error/deleteError', this.getError());
            }
        },

        onChange(event) {
            this.currentValue = this.getValueFromEvent(event);

            this.$emit('change', this.currentValue);

            if (['checkbox', 'radio', 'switch'].includes(this.type)) {
                this.$emit('input', this.currentValue);
            }

            if (this.hasError()) {
                this.$store.commit('error/deleteError', this.getError());
            }
        },

        /**
         * Get the correct value from a input event based on the input type.
         *
         * @param event
         * @returns {*}
         */
        getValueFromEvent(event) {
            let value = event.target.value;

            if (event.target.type === 'checkbox') {
                value = event.target.checked;
            }

            return this.convertValueType(value);
        },

        /**
         * Convert the value to the correct type based on the bound property.
         *
         * @param value
         * @returns {*}
         */
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
        },

        /**
         * Get the current error of the field.
         * Will look for errors for the bound value in the global error state.
         * You can also pass an error message directly via property.
         *
         * @returns {*}
         */
        getError() {
            if (this.errorMessage !== null && this.errorMessage.length > 0) {
                return { detail: this.errorMessage };
            }

            if (this.boundExpressionPath.length <= 0) {
                return null;
            }

            return this.boundExpressionPath.reduce((obj, key) => {
                return (obj !== null && obj[key]) ? obj[key] : null;
            }, this.$store.state.error);
        },

        hasError() {
            return (this.errorMessage !== null && this.errorMessage.length > 0) || this.getError() !== null;
        },

        hasErrorCls() {
            return !this.isValid || this.hasError();
        }
    }
});
