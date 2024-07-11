import template from './sw-popover.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-popover and mt-floating-ui. Autoswitches between the two components.
 */
Component.register('sw-popover', {
    template,

    compatConfig: Shopware.compatConfig,

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
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-popover',
                // eslint-disable-next-line max-len
                'The old usage of "sw-popover" is deprecated and will be removed in v6.7.0.0. Please use "mt-floating-ui" instead.',
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

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return {
                    ...this.$slots,
                    ...this.$scopedSlots,
                };
            }

            return this.$slots;
        },
    },
});
