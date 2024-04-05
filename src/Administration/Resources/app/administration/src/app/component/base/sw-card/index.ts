import template from './sw-card.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-card and mt-card. Autoswitches between the two components.
 */
Component.register('sw-card', {
    template,

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-card',
                // eslint-disable-next-line max-len
                'The old usage of "sw-card" is deprecated and will be removed in v6.7.0.0. Please use "mt-card" instead.',
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
