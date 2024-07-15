import template from './sw-alert.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-alert and mt-banner. Switches between the two components.
 */
Component.register('sw-alert', {
    template,

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-alert',
                // eslint-disable-next-line max-len
                'The old usage of "sw-alert" is deprecated and will be removed in v6.7.0.0. Please use "mt-banner" instead.',
            );

            return false;
        },

        // eslint-disable-next-line @typescript-eslint/ban-types
        listeners(): Record<string, Function | Function[]> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },
});
