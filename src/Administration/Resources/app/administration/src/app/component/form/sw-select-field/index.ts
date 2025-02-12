import template from './sw-select-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-select-field and mt-select-field. Autoswitches between the two components.
 */
Component.register('sw-select-field', {
    template,

    props: {
        options: {
            type: Array,
            required: false,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-select-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-select-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-select" instead.',
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
