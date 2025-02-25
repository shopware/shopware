import template from './sw-colorpicker.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-colorpicker and mt-colorpicker. Autoswitches between the two components.
 */
Component.register('sw-colorpicker', {
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
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-colorpicker',
                // eslint-disable-next-line max-len
                'The old usage of "sw-colorpicker" is deprecated and will be removed in v6.7.0.0. Please use "mt-colorpicker" instead.',
            );

            return false;
        },

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
