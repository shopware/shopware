import template from './sw-number-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-number-field and mt-number-field. Autoswitches between the two components.
 */
Component.register('sw-number-field', {
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
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-number-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-number-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-number-field" instead.',
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
                // For providing backwards compatibility with the old sw-number-field component
                this.$emit('update:value', value);
                this.$emit('change', value);
            },
        },
    },

    methods: {
        getSlots() {
            const allSlots = {
                ...this.$slots,
                ...this.$scopedSlots,
            };

            return allSlots;
        },
    },
});
