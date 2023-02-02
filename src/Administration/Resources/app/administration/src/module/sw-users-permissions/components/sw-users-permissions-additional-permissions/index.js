import template from './sw-users-permissions-additional-permissions.html.twig';
import './sw-users-permissions-additional-permissions.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-users-permissions-additional-permissions', {
    template,

    inject: ['privileges'],

    props: {
        role: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {};
    },

    computed: {
        additionalPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges.filter(
                privilege => privilege.category === 'additional_permissions' && privilege.key !== 'app',
            );
        },

        appPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges.filter(
                privilege => privilege.category === 'additional_permissions' && privilege.key === 'app',
            );
        },
    },

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    created() {
        this.createdComponent();
    },

    methods: {
        /**
         * @deprecated tag:v6.5.0 - Will be removed
         */
        createdComponent() {},

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
        },

        changeAllAppPermissionsForKey(permissionKey, isSelected) {
            this.appPermissions.forEach(permission => {
                Object.keys(permission.roles).forEach(role => {
                    const identifier = `app.${role}`;

                    if (isSelected) {
                        if (this.role.privileges.includes(identifier)) {
                            return;
                        }

                        this.role.privileges.push(identifier);
                    } else {
                        this.role.privileges = this.role.privileges.filter(p => p !== identifier);
                    }
                });
            });
        },
    },
});
