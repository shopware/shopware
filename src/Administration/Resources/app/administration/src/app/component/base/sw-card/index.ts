import template from './sw-card.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-card and mt-card. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-card instead.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },
    },
});
