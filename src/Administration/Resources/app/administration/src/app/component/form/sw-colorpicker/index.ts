import template from './sw-colorpicker.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-colorpicker and mt-colorpicker. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-colorpicker instead.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        /**
         * For providing backwards compatibility with the old sw-colorpicker component
         */
        value: {
            type: [
                Number,
                String,
            ],
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
            get() {
                if (this.value !== null) {
                    return this.value;
                }

                return this.modelValue;
            },
            set(value: number) {
                // For providing backwards compatibility with the old sw-colorpicker component
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
