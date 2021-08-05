import template from './sw-textarea-field.html.twig';
import './sw-textarea-field.scss';

const { Component, Mixin } = Shopware;

/**
 * @description textarea input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-textarea-field type="textarea" label="Name" placeholder="placeholder goes here..."></sw-textarea-field>
 */
Component.register('sw-textarea-field', {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },

        placeholder: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            currentValue: this.value || '',
        };
    },

    watch: {
        value() { this.currentValue = this.value; },
    },

    methods: {
        onInput(event) {
            this.$emit('input', event.target.value);
        },

        onChange(event) {
            this.$emit('change', event.target.value);
        },
    },
});
