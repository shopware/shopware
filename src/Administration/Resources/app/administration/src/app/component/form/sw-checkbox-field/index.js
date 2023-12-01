import template from './sw-checkbox-field.html.twig';
import './sw-checkbox-field.scss';

const { Component, Mixin } = Shopware;
const utils = Shopware.Utils;

/**
 * @package admin
 *
 * @private
 * @description Boolean input field based on checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-checkbox-field v-model="aBooleanProperty" label="Name"></sw-checkbox-field>
 */
Component.register('sw-checkbox-field', {
    template,
    inheritAttrs: false,

    emits: ['update:value'],

    inject: ['feature'],

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
    ],

    props: {
        id: {
            type: String,
            required: false,
            default: utils.createId(),
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        label: {
            type: String,
            required: false,
            default: undefined,
        },

        value: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: null,
        },

        inheritedValue: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: null,
        },

        ghostValue: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: null,
        },

        error: {
            type: Object,
            required: false,
            default: null,
        },

        bordered: {
            type: Boolean,
            required: false,
            default: false,
        },

        padded: {
            type: Boolean,
            required: false,
            default: false,
        },

        partlyChecked: {
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
        swCheckboxFieldClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.disabled,
                'is--inherited': this.isInherited,
                'is--partly-checked': this.isPartlyChecked,
                'sw-field__checkbox--ghost': this.ghostValue,
            };
        },

        swCheckboxFieldContentClasses() {
            return {
                'is--bordered': this.bordered,
                'is--padded': this.padded,
            };
        },

        identification() {
            return `sw-field--${this.id}`;
        },

        hasError() {
            return this.error && this.error.code !== 0;
        },

        inputState() {
            return this.currentValue || false;
        },

        isInheritanceField() {
            if (this.$attrs.isInheritanceField) {
                return true;
            }
            return this.inheritedValue !== null;
        },

        isInherited() {
            if (this.$attrs.isInherited) {
                return true;
            }
            return this.isInheritanceField && this.currentValue === null;
        },

        isPartlyChecked() {
            return this.partlyChecked && !this.inputState;
        },

        iconName() {
            return this.isPartlyChecked ? 'regular-minus-xxs' : 'regular-checkmark-xxs';
        },
    },

    watch: {
        value() { this.currentValue = this.value; },
    },

    methods: {
        onChange(changeEvent) {
            this.$emit('update:value', changeEvent.target.checked);
        },
    },
});
