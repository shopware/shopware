import template from './sw-switch-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-switch-field and mt-switch. Autoswitches between the two components.
 */
Component.register('sw-switch-field', {
    template,

    emits: ['update:value'],

    props: {
        value: {
            type: Boolean,
            required: false,
        },

        checked: {
            type: Boolean,
            required: false,
        },
    },

    computed: {
        checkedValue() {
            return this.value || this.checked;
        },
    },

    methods: {
        onChangeHandler(value) {
            // For backwards compatibility
            this.$emit('update:value', value);
        },
    },
});
