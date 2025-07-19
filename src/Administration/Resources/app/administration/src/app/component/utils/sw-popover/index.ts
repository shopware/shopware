import template from './sw-popover.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-popover and mt-floating-ui. Autoswitches between the two components.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        isOpened: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-popover',
                // eslint-disable-next-line max-len
                'The old usage of "sw-popover" is deprecated and will be removed in v6.8.0.0. Please use "mt-floating-ui" instead.',
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
