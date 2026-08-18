/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-role-view-detailed.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
    ],

    props: {
        role: {
            type: Object,
            required: false,
            default: null,
        },
        detailedPrivileges: {
            type: Array,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};
