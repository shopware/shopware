import template from './sw-alert.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
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
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
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
    },
});
