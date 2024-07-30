import template from './sw-icon.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-icon and mt-icon. Autoswitches between the two components.
 */
Component.register('sw-icon', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        name: {
            type: String,
            required: true,
        }
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-icon',
                // eslint-disable-next-line max-len
                'The old usage of "sw-icon" is deprecated and will be removed in v6.7.0.0. Please use "mt-icon" instead.',
            );

            return false;
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        }
    },
});
