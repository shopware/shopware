import template from './sw-skeleton-bar.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-skeleton-bar and mt-skeleton-bar. Autoswitches between the two components.
 */
Component.register('sw-skeleton-bar', {
    template,

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-skeleton-bar',
                // eslint-disable-next-line max-len
                'The old usage of "sw-skeleton-bar" is deprecated and will be removed in v6.7.0.0. Please use "mt-skeleton-bar" instead.',
            );

            return false;
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },
});
