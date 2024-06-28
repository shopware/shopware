/**
 * @package admin
 */

import { compatUtils } from '@vue/compat';
import template from './sw-contextual-field.html.twig';
import './sw-contextual-field.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-contextual-field', {
    template,
    inheritAttrs: false,

    computed: {
        hasPrefix() {
            if (compatUtils.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return this.$scopedSlots.hasOwnProperty('sw-contextual-field-prefix')
                    && this.$scopedSlots['sw-contextual-field-prefix']({}) !== undefined;
            }

            return this.$slots.hasOwnProperty('sw-contextual-field-prefix')
                && this.$slots['sw-contextual-field-prefix']({}) !== undefined;
        },

        hasSuffix() {
            if (compatUtils.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return this.$scopedSlots.hasOwnProperty('sw-contextual-field-suffix')
                    && this.$scopedSlots['sw-contextual-field-suffix']({}) !== undefined;
            }

            return this.$slots.hasOwnProperty('sw-contextual-field-suffix')
                && this.$slots['sw-contextual-field-suffix']({}) !== undefined;
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (compatUtils.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },
});
