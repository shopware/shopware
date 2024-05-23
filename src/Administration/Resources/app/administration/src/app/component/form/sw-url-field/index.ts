import template from './sw-url-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-url-field and mt-url-field. Autoswitches between the two components.
 */
Component.register('sw-url-field', {
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
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-url-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-url-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-url-field" instead.',
            );

            return false;
        },

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
            const allSlots = {
                ...this.$slots,
                ...this.$scopedSlots,
            };

            return allSlots;
        },
    },
});
