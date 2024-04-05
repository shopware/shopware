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
                'sw-button',
                // eslint-disable-next-line max-len
                'The old usage of "sw-button" is deprecated and will be removed in v6.7.0.0. Please use "mt-button" instead.',
            );

            return false;
        },
    },
});
