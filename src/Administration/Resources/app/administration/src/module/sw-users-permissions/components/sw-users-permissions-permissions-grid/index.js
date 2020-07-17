import template from './sw-users-permissions-permissions-grid.html.twig';
import './sw-users-permissions-permissions-grid.scss';

const { Component } = Shopware;

Component.register('sw-users-permissions-permissions-grid', {
    template,

    inject: ['privileges'],

    props: {
        role: {
            type: Object,
            required: true
        }
    },

    computed: {
        permissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges.filter(privilege => privilege.category === 'permissions');
        },

        roles() {
            return [
                'viewer',
                'editor',
                'creator',
                'deleter'
            ];
        }
    },

    methods: {
        changePermission(permissionKey, permissionRole) {
            const identifier = `${permissionKey}.${permissionRole}`;

            if (this.role.privileges.includes(identifier)) {
                this.removePermission(identifier);
            } else {
                this.addPermission(identifier);
            }
        },

        addPermission(identifier) {
            if (this.role.privileges.includes(identifier)) {
                return;
            }

            this.role.privileges.push(identifier);

            this.addDependenciesForRole(identifier);
        },

        addDependenciesForRole(identifier) {
            const privilegeRole = this.privileges.getPrivilegeRole(identifier);

            privilegeRole.dependencies.forEach((dependencyIdentifier) => {
                this.addPermission(dependencyIdentifier);
            });
        },

        removePermission(identifier) {
            this.role.privileges = this.role.privileges.filter(privilege => {
                return privilege !== identifier;
            });
        },

        isPermissionSelected(permissionKey, permissionRole) {
            return this.role.privileges.some(privilege => {
                return privilege === `${permissionKey}.${permissionRole}`;
            });
        },

        changeAllPermissionsForKey(permissionKey) {
            const isAllSelected = this.allPermissionsForKeySelected(permissionKey);

            this.roles.forEach(role => {
                const identifier = `${permissionKey}.${role}`;

                if (isAllSelected) {
                    this.removePermission(identifier);
                } else {
                    this.addPermission(identifier);
                }
            });
        },

        allPermissionsForKeySelected(permissionKey) {
            const containsUnselected = this.roles.some(permissionRole => {
                return !this.isPermissionSelected(permissionKey, permissionRole);
            });

            return !containsUnselected;
        }
    }
});
