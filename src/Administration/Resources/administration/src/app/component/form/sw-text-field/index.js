import { Mixin } from 'src/core/shopware';
import template from './sw-text-field.html.twig';

/**
 * @protected
 * @description Simple text field.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-text-field type="text" label="Name" placeholder="placeholder goes here..."></sw-text-field>
 */
export default {
    name: 'sw-text-field',
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('validation')
    ],

    props: {
        value: {
            required: false
        },

        placeholder: {
            type: String,
            required: false,
            default: ''
        },

        copyable: {
            type: Boolean,
            required: false,
            default: false
        },

        copyableTooltip: {
            type: Boolean,
            required: false,
            default: false
        },

        inheritedValue: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            currentValue: this.value
        };
    },

    computed: {
        hasPrefix() {
            return this.$scopedSlots.hasOwnProperty('prefix');
        },

        hasSuffix() {
            return this.$scopedSlots.hasOwnProperty('suffix');
        },

        additionalListeners() {
            const additionalListeners = Object.assign({}, this.$listeners);

            delete additionalListeners.input;
            delete additionalListeners.change;

            return additionalListeners;
        }
    },

    watch: {
        value(value) {
            this.currentValue = value;
        }
    },

    methods: {
        onChange(event) {
            this.resetFormError();
            this.currentValue = event.target.value || '';
            this.$emit('change', this.currentValue);
        },

        onInput(event) {
            this.resetFormError();
            this.currentValue = event.target.value || '';
            this.$emit('input', this.currentValue);
        },

        restoreInheritance() {
            this.$emit('input', null);
        }
    }
};
