import template from './sw-users-permissions-role-view-general.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Shopware.Component.register('sw-users-permissions-role-view-general', {
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
});
