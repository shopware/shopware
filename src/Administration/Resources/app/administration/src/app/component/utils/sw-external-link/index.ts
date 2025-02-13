import template from './sw-external-link.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-external-link and mt-external-link. Autoswitches between the two components.
 */
Component.register('sw-external-link', {
    template,

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-external-link',
                // eslint-disable-next-line max-len
                'The old usage of "sw-external-link" is deprecated and will be removed in v6.7.0.0. Please use "mt-external-link" instead.',
            );

            return false;
        },
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },
    },
});
