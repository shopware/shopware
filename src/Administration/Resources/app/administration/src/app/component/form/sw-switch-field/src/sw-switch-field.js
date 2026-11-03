import template from './sw-switch-field.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-switch-field and mt-switch. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-switch instead.
 */
export default {
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

        deprecated: {
            type: Boolean,
            required: false,
            default: false,
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
};
