/**
 * @sw-package fundamentals@framework
 */

import template from './sw-users-permissions-user-sso-managed-roles.html.twig';
import './sw-users-permissions-user-sso-managed-roles.scss';

const { Component } = Shopware;

/**
 * Read-only presentation of a user's SSO provisioning state on the user detail page (feature flag
 * ADMIN_AUTH): an info banner naming the identity providers and a badge list of the role
 * assignments (and admin flag) that are managed — i.e. re-applied on every login — by the SSO role
 * sync and therefore cannot be edited here.
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    props: {
        providerLabels: {
            type: Array as PropType<string[]>,
            required: false,
            default: () => [],
        },
        roles: {
            type: Array as PropType<Array<{ id: string; name: string }>>,
            required: false,
            default: () => [],
        },
        ssoManagedAdmin: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        providerList(): string {
            if (this.providerLabels.length === 0) {
                return this.$t('sw-users-permissions.ssoManaged.fallbackProvider');
            }

            return this.providerLabels.join(', ');
        },

        hasManagedEntries(): boolean {
            return this.ssoManagedAdmin || this.roles.length > 0;
        },
    },
});
