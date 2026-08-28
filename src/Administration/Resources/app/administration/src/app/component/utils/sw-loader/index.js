import template from './sw-loader.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-loader and mt-loader. Autoswitches between the two components.
 */
export default {
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
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'The old usage of "sw-loader" is deprecated and will be removed in v6.8.0.0. Please use "mt-loader" instead.',
            );

            return false;
        },
    },

    methods: {
        getSlots() {
            return this.$slots;
        },
    },
};
