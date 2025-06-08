import template from './sw-button.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-button and mt-button. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-button instead
 */
export default {
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

        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    methods: {
        onClick(event) {
            event.stopImmediatePropagation();
            // Important: Do not emit the click event again, it is already emitted by the button

            // Check if deprecated routerLink is used
            if (this.routerLink) {
                // Use router push to navigate to the new page
                this.$router.push(this.routerLink);
            }
        },
    },
};
