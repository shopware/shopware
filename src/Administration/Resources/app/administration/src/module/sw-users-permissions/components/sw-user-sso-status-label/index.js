import template from './sw-user-sso-status-label.html.twig';

/**
 * @internal
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    name: 'sw-user-sso-status-label',
    template,

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    computed: {
        status() {
            if (this.user.active) {
                return 'active';
            }

            if (!this.user.active && this.user.email === this.user.firstName && this.user.email === this.user.lastName) {
                return 'invited';
            }

            return 'inactive';
        },

        statusText() {
            return this.$tc(`sw-users-permissions.sso.user-listing.status-label.${this.status}`);
        },

        variant() {
            switch (this.status) {
                case 'active':
                    return 'success';
                case 'invited':
                    return 'warning';
                default:
                    return 'danger';
            }
        },
    },
};
