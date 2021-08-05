import template from './sw-user-card.html.twig';
import './sw-user-card.scss';

const { Component } = Shopware;

/**
 * @public
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
 * <template slot="actions">
 *     <sw-button size="small" disabled>
 *         <sw-icon name="small-pencil" small></sw-icon>
 *         Edit user
 *     </sw-button>
 *
 *     <sw-button size="small" disabled>
 *         <sw-icon name="default-lock-key" small></sw-icon>
 *         Change password
 *      </sw-button>
 * </template>
 * </sw-user-card>
 */
Component.register('sw-user-card', {
    template,

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
    },
});
