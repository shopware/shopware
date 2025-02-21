import template from './sw-textarea-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-textarea-field and mt-textarea. Autoswitches between the two components.
 */
Component.register('sw-textarea-field', {
    template,

    props: {
        placeholder: {
            type: String,
            required: false,
            default: undefined,
        },

        value: {
            type: String,
            required: false,
            default: undefined,
        },

        modelValue: {
            type: String,
            required: false,
            default: undefined,
        },
    },

    computed: {
        realValue: {
            get() {
                return this.modelValue || this.value;
            },
            set(value: string) {
                this.$emit('update:modelValue', value);
            },
        },
    },

    methods: {
        getSlots() {
            return this.$slots;
        },
    },
});
