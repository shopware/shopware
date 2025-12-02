import template from './sw-url-field.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-url-field and mt-url-field. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-url-field instead.
 */
export default Shopware.Component.wrapComponentConfig({
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
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },
    },
});
