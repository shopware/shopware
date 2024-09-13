/**
 * @package admin
 */

import template from './sw-user-card.html.twig';
import './sw-user-card.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Renders a compact user information card using the provided user data.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-user-card title="Account" :user="{
 *     email: 'test@example.com',
 *     active: true,
 *     addresses: [{
 *         salutation: 'Mister',
 *         title: 'Doctor',
 *         firstName: 'John',
 *         lastName: 'Doe',
 *         street: 'Main St 123',
 *         zipcode: '12456',
 *         city: 'Anytown',
 *         country: { name: 'Germany' }
 *     }],
 *     salutation: 'Mister',
 *     title: 'Doctor',
 *     firstName: 'John',
 *     lastName: 'Doe',
 *     street: 'Main St 123',
 *     zipcode: '12456',
 *     city: 'Anytown',
 *     country: { name: 'Germany' }
 * }">
 * <template #actions>
 *     <sw-button size="small" disabled>
 *         <sw-icon name="regular-pencil-s" small></sw-icon>
 *         Edit user
 *     </sw-button>
 *
 *     <sw-button size="small" disabled>
 *         <sw-icon name="regular-key" small></sw-icon>
 *         Change password
 *      </sw-button>
 * </template>
 * </sw-user-card>
 */
Component.register('sw-user-card', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        user: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
        title: {
            type: String,
            required: true,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        hasActionSlot() {
            return !!this.$slots.actions;
        },
        hasAdditionalDataSlot() {
            return !!this.$slots['data-additional'];
        },
        hasSummarySlot() {
            return !!this.$slots.summary;
        },

        moduleColor() {
            if (!this.$route.meta.$module) {
                return '';
            }
            return this.$route.meta.$module.color;
        },

        salutationFilter() {
            return Shopware.Filter.getByName('salutation');
        },
    },
});
