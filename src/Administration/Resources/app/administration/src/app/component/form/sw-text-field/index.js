import template from './sw-text-field.html.twig';

const { Component, Mixin } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @protected
 * @description Simple text field.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-text-field label="Name" placeholder="placeholder goes here..."></sw-text-field>
 */
Component.register('sw-text-field', {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
        Mixin.getByName('validation'),
    ],

    props: {
        // FIXME: add type and default value to property
        // eslint-disable-next-line vue/require-prop-types, vue/require-default-prop
        value: {
            required: false,
        },

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        copyable: {
            type: Boolean,
            required: false,
            default: false,
        },

        copyableTooltip: {
            type: Boolean,
            required: false,
            default: false,
        },

        idSuffix: {
            type: String,
            required: false,
            default() {
                return '';
            },
        },
    },

    data() {
        return {
            currentValue: this.value,
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
            const additionalListeners = { ...this.$listeners };

            delete additionalListeners.input;
            delete additionalListeners.change;

            return additionalListeners;
        },
    },

    watch: {
        value(value) {
            this.currentValue = value;
        },
    },

    methods: {
        onChange(event) {
            this.$emit('change', event.target.value || '');
        },

        onInput(event) {
            this.$emit('input', event.target.value);
        },

        restoreInheritance() {
            this.$emit('input', null);
        },

        createInputId(identification) {
            if (!this.idSuffix || this.idSuffix.length <= 0) {
                return identification;
            }

            return `${identification}-${this.idSuffix}`;
        },
    },
});
