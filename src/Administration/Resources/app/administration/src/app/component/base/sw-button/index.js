import template from './sw-button.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-button and mt-button. Autoswitches between the two components.
 */
Component.register('sw-button', {
    template,

    props: {
        routerLink: {
            type: [String, Object],
            default: null,
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
                'sw-button',
                // eslint-disable-next-line max-len
                'The old usage of "sw-button" is deprecated and will be removed in v6.7.0.0. Please use "mt-button" instead.',
            );

            return false;
        },
    },

    methods: {
        onClick() {
            // Important: Do not emit the click event again, it is already emitted by the button

            // Check if deprecated routerLink is used
            if (this.routerLink) {
                // Use router push to navigate to the new page
                this.$router.push(this.routerLink);
            }
        },
    },
});
