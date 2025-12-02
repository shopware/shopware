import template from './sw-number-field.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-number-field and mt-number-field. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-number-field instead.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        /**
         * For providing backwards compatibility with the old sw-number-field component
         */
        value: {
            type: Number,
            required: false,
            default: null,
        },

        modelValue: {
            type: Number,
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
        currentValue: {
            get(): number | undefined {
                if (this.value !== null) {
                    return this.value;
                }

                return this.modelValue;
            },
            set(value: number) {
                // For providing backwards compatibility with the old sw-number-field component
                this.$emit('update:value', value);
                this.$emit('change', value);
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
