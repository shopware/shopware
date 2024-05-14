import template from './sw-loader.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-loader and mt-loader. Autoswitches between the two components.
 */
Component.register('sw-loader', {
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
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-loader',
                // eslint-disable-next-line max-len
                'The old usage of "sw-loader" is deprecated and will be removed in v6.7.0.0. Please use "mt-loader" instead.',
            );

            return false;
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
