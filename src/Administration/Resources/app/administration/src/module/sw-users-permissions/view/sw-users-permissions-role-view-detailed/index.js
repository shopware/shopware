/**
 * @package services-settings
 */
import template from './sw-users-permissions-role-view-detailed.html.twig';
import './sw-users-permissions-role-view-detailed.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'acl',
    ],

    props: {
        role: {
            type: Object,
            required: true,
        },

        detailedPrivileges: {
            type: Array,
            required: true,
        },
    },
};
