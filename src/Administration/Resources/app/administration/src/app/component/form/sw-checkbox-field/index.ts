import template from './sw-checkbox-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-checkbox-field and mt-checkbox-field. Autoswitches between the two components.
 */
Component.register('sw-checkbox-field', {
    template,

    props: {},

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-checkbox-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-checkbox-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-checkbox" instead.',
            );

            return false;
        },
    },

    methods: {
        getSlots() {
            const allSlots = {
                ...this.$slots,
                ...this.$scopedSlots,
            };

            return allSlots;
        },
    },
});
