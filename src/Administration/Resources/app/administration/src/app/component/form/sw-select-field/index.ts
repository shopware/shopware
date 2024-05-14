import template from './sw-select-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-select-field and mt-select-field. Autoswitches between the two components.
 */
Component.register('sw-select-field', {
    template,

    props: {
        options: {
            type: Array,
            required: false,
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
                'sw-select-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-select-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-select" instead.',
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
