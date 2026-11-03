import template from './sw-password-field.html.twig';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-password-field and mt-password-field. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-password-field instead.
 */
export default wrapComponentConfig({
    template,

    emits: ['update:value', 'update:modelValue'],

    props: {
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

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        realValue: {
            get() {
                return this.modelValue || this.value;
            },
            set(value: string) {
                this.$emit('update:value', value);
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
