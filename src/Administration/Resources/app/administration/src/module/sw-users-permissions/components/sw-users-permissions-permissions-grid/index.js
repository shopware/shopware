/**
 * @package system-settings
 */
import template from './sw-users-permissions-permissions-grid.html.twig';
import './sw-users-permissions-permissions-grid.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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

    computed: {
        permissionsWithParents() {
            const permissionsWithParents = [];

            this.parents.forEach(parent => {
                permissionsWithParents.push({
                    type: 'parent',
                    value: parent,
                });

                const children = this.getPermissionsForParent(parent);

                children.forEach(child => {
                    permissionsWithParents.push(child);
                });
            });

            return permissionsWithParents;
        },


        permissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges
                .filter(privilege => privilege.category === 'permissions')
                .sort((a, b) => {
                    const labelA = this.$tc(`sw-privileges.permissions.${a.key}.label`);
                    const labelB = this.$tc(`sw-privileges.permissions.${b.key}.label`);

                    return labelA.localeCompare(labelB);
                });
        },

        parents() {
            return this.permissions
                .reduce((parents, privilege) => {
                    if (parents.includes(privilege.parent)) {
                        return parents;
                    }

                    return [...parents, privilege.parent];
                }, [])
                .sort((a, b) => {
                    const labelA = this.$tc(`sw-privileges.permissions.parents.${a || 'other'}`);
                    const labelB = this.$tc(`sw-privileges.permissions.parents.${b || 'other'}`);

                    return labelA.localeCompare(labelB);
                });
        },

        usedDependencies() {
            const dependencies = new Set();

            this.role.privileges.forEach(privilegeKey => {
                const privilegeRole = this.privileges.getPrivilegeRole(privilegeKey);

                if (!privilegeRole) {
                    return;
                }

                privilegeRole.dependencies.forEach(dependency => {
                    dependencies.add(dependency);
                });
            });

            return [...dependencies];
        },

        roles() {
            return [
                'viewer',
                'editor',
                'creator',
                'deleter',
            ];
        },
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

            if (!privilegeRole) {
                return;
            }

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

        isPermissionDisabled(permissionKey, permissionRole) {
            return this.usedDependencies.includes(`${permissionKey}.${permissionRole}`);
        },

        changeAllPermissionsForKey(permissionKey) {
            const areAllSelected = this.allPermissionsForKeySelected(permissionKey);

            this.roles.forEach(role => {
                const identifier = `${permissionKey}.${role}`;
                const privilegeExists = this.privileges.existsPrivilege(identifier);

                if (!privilegeExists) {
                    return;
                }

                if (areAllSelected) {
                    this.removePermission(identifier);
                } else {
                    this.addPermission(identifier);
                }
            });
        },

        allPermissionsForKeySelected(permissionKey) {
            const containsUnselected = this.roles.some(permissionRole => {
                const doesExist = this.privileges.existsPrivilege(`${permissionKey}.${permissionRole}`);

                if (!doesExist) {
                    return false;
                }

                return !this.isPermissionSelected(permissionKey, permissionRole);
            });

            return !containsUnselected;
        },

        getPermissionsForParent(parentKey) {
            return this.permissions.filter(permission => {
                return permission.parent === parentKey;
            });
        },

        areAllChildrenRolesSelected(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            const hasUnselected = permissionsForParent.some(permission => {
                if (permission.roles[roleKey] === undefined) {
                    return false;
                }

                return !this.isPermissionSelected(permission.key, roleKey);
            });

            return !hasUnselected;
        },

        areAllChildrenWithAllRolesSelected(parentKey) {
            return this.roles.every(roleKey => {
                return this.areAllChildrenRolesSelected(parentKey, roleKey);
            });
        },

        areSomeChildrenRolesSelected(parentKey, roleKey, ignoreMissingPrivilege = true) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            return permissionsForParent.some(permission => {
                if (!ignoreMissingPrivilege) {
                    const privilegeExists = this.privileges.existsPrivilege(`${permission.key}.${roleKey}`);

                    if (!privilegeExists) {
                        return true;
                    }
                }

                return this.isPermissionSelected(permission.key, roleKey);
            });
        },

        areSomeChildrenWithAllRolesSelected(parentKey) {
            return this.roles.every(roleKey => {
                return this.areSomeChildrenRolesSelected(parentKey, roleKey, false);
            });
        },

        isParentRoleDisabled(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            return permissionsForParent.every(permission => {
                return this.isPermissionDisabled(permission.key, roleKey);
            });
        },

        toggleAllChildrenWithRole(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);
            const allChildrenRolesSelected = this.areAllChildrenRolesSelected(parentKey, roleKey);

            permissionsForParent.forEach(permission => {
                if (!permission.roles[roleKey]) {
                    return;
                }

                const identifier = `${permission.key}.${roleKey}`;

                if (this.isPermissionDisabled(permission.key, roleKey)) {
                    return;
                }

                if (allChildrenRolesSelected) {
                    this.removePermission(identifier);
                } else {
                    this.addPermission(identifier);
                }
            });
        },

        toggleAllChildrenWithAllRoles(parentKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);
            const allChildrenWithAllRolesSelected = this.areAllChildrenWithAllRolesSelected(parentKey);

            return this.roles.forEach(roleKey => {
                permissionsForParent.forEach(permission => {
                    const identifier = `${permission.key}.${roleKey}`;

                    if (allChildrenWithAllRolesSelected) {
                        this.removePermission(identifier);
                    } else {
                        this.addPermission(identifier);
                    }
                });
            });
        },

        parentRoleHasChildRoles(parentKey, roleKey) {
            return this.getPermissionsForParent(parentKey).some(currentRole => {
                return currentRole.roles[roleKey] !== undefined;
            });
        },
    },
};
