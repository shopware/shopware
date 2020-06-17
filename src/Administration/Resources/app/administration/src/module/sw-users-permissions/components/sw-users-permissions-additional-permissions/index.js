import template from './sw-users-permissions-additional-permissions.html.twig';
import './sw-users-permissions-additional-permissions.scss';

const { Component } = Shopware;

Component.register('sw-users-permissions-additional-permissions', {
    template,

    inject: ['privileges'],

    props: {
        role: {
            type: Object,
            required: true
        }
    },

    data() {
        return {};
    },

    computed: {
        additionalPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges.filter(privilege => privilege.category === 'additional_permissions');
        }
    },

    methods: {
        isPrivilegeSelected(privilegeKey) {
            if (!this.role.privileges) {
                return false;
            }

            return this.role.privileges.includes(privilegeKey);
        },

        onSelectPrivilege(privilegeKey, isSelected) {
            if (isSelected) {
                this.role.privileges.push(privilegeKey);
            } else {
                this.role.privileges = this.role.privileges.filter(p => p !== privilegeKey);
            }
        }
    }
});
