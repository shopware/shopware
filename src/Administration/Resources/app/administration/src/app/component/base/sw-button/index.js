import template from './sw-button.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-button and mt-button. Autoswitches between the two components.
 */
Component.register('sw-button', {
    template,

    props: {
        routerLink: {
            type: [
                String,
                Object,
            ],
            default: null,
            required: false,
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
