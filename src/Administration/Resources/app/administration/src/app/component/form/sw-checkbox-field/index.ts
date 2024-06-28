// @ts-expect-error
import { compatUtils } from '@vue/compat';
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

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (compatUtils.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (compatUtils.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return {
                    ...this.$slots,
                    ...this.$scopedSlots,
                };
            }

            return this.$slots;
        },
    },
});
