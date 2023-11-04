/**
 * @package system-settings
 */
import template from './sw-users-permissions-role-view-general.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
    ],

    props: {
        role: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('role', [
            'name',
            'description',
        ]),
    },
};
