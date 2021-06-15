import template from './sw-users-permissions-role-view-detailed.html.twig';
import './sw-users-permissions-role-view-detailed.scss';

Shopware.Component.register('sw-users-permissions-role-view-detailed', {
    template,

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
});
