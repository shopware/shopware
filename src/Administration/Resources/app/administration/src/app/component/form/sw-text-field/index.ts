import template from './sw-text-field.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-text-field and mt-text-field. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-text-field instead.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        modelValue: {
            type: String,
            required: false,
            default: null,
        },

        value: {
            type: String,
            required: false,
            default: null,
        },

        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        compatValue: {
            get() {
                if (this.value === null || this.value === undefined) {
                    return this.modelValue;
                }

                return this.value;
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

        handleUpdateModelValue(event: unknown) {
            this.$emit('update:modelValue', event);

            // Emit old event for backwards compatibility
            this.$emit('update:value', event);
        },
    },
});
