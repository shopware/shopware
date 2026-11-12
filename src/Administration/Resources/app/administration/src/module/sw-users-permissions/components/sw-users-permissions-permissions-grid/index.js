/**
 * @sw-package fundamentals@framework
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
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            expandedHiddenAccessKeys: [],
        };
    },

    computed: {
        permissionsWithParents() {
            const permissionsWithParents = [];

            this.parents.forEach((parent) => {
                permissionsWithParents.push({
                    type: 'parent',
                    value: parent,
                });

                const children = this.getPermissionsForParent(parent);

                children.forEach((child, index) => {
                    permissionsWithParents.push({ ...child, groupIndex: index });
                });
            });

            return permissionsWithParents;
        },

        permissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges
                .filter((privilege) => privilege.category === 'permissions')
                .sort((a, b) => {
                    const labelA = this.$t(`sw-privileges.permissions.${a.key}.label`);
                    const labelB = this.$t(`sw-privileges.permissions.${b.key}.label`);

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
                .sort((a, b) => this.compareParents(a, b));
        },

        usedDependencies() {
            const dependencies = new Set();

            this.role.privileges.forEach((privilegeKey) => {
                const privilegeRole = this.privileges.getPrivilegeRole(privilegeKey);

                if (!privilegeRole) {
                    return;
                }

                privilegeRole.dependencies.forEach((dependency) => {
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

        // Mirrors the order of the main navigation; unknown parents follow alphabetically, "other" is last.
        parentOrder() {
            return [
                'catalogues',
                'orders',
                'customers',
                'content',
                'marketing',
                'settings',
            ];
        },

        // Parents whose label lives under a snippet key that differs from the privilege parent itself.
        parentSnippetKeys() {
            return {
                catalogues: 'products',
            };
        },

        selectedPrivilegeKeys() {
            return this.role?.privileges ?? [];
        },

        /**
         * Per row: what the row grants on data of other modules, at its selected level or at its lowest level.
         */
        hiddenAccessByKey() {
            return this.permissions.reduce((hiddenAccessByKey, permission) => {
                hiddenAccessByKey[permission.key] = this.privileges.getHiddenAccessForAdminPrivilegeKeys(
                    this.rowPreviewKeys(permission),
                );

                return hiddenAccessByKey;
            }, {});
        },

        indirectAccessByOwner() {
            return this.privileges.getIndirectAccess(this.selectedPrivilegeKeys).reduce((byOwner, access) => {
                byOwner[access.ownerKey] = access;

                return byOwner;
            }, {});
        },

        personalDataAreas() {
            return Object.values(this.indirectAccessByOwner)
                .filter((access) => access.containsPersonalData)
                .map((access) => {
                    const privileges = [
                        ...new Set(
                            access.entries.filter((entry) => entry.containsPersonalData).map((entry) => entry.privilege),
                        ),
                    ].sort();

                    return {
                        key: access.ownerKey,
                        label: this.ownerLabel(access.ownerKey),
                        privileges: privileges.join(', '),
                    };
                })
                .sort((a, b) => a.label.localeCompare(b.label));
        },
    },

    methods: {
        rowPreviewKeys(permission) {
            const selectedKeys = Object.keys(permission.roles)
                .map((role) => `${permission.key}.${role}`)
                .filter((identifier) => this.selectedPrivilegeKeys.includes(identifier));

            if (selectedKeys.length > 0) {
                return selectedKeys;
            }

            const lowestRole = this.roles.find((role) => permission.roles[role]) ?? Object.keys(permission.roles)[0];

            return lowestRole ? [`${permission.key}.${lowestRole}`] : [];
        },

        hiddenAccess(permissionKey) {
            return this.hiddenAccessByKey[permissionKey] ?? [];
        },

        /**
         * Write access and personal data need attention, plain reads of other areas don't.
         */
        needsAttention(group) {
            return group.containsWrite || group.containsPersonalData;
        },

        hasHiddenAttention(permissionKey) {
            return this.hiddenAccess(permissionKey).some((group) => this.needsAttention(group));
        },

        /**
         * Areas that need attention come first, plain reads follow.
         */
        sortedHiddenAccess(permissionKey) {
            const groups = this.hiddenAccess(permissionKey);

            return [
                ...groups.filter((group) => this.needsAttention(group)),
                ...groups.filter((group) => !this.needsAttention(group)),
            ];
        },

        hiddenAccessTooltip(group) {
            return group.entries
                .map((entry) => `${this.operationsLabel(entry.operations)} ${entry.entities.join(', ')}`)
                .join('; ');
        },

        isHiddenAccessExpanded(permissionKey) {
            return this.expandedHiddenAccessKeys.includes(permissionKey);
        },

        toggleHiddenAccess(permissionKey) {
            if (this.isHiddenAccessExpanded(permissionKey)) {
                this.expandedHiddenAccessKeys = this.expandedHiddenAccessKeys.filter((key) => key !== permissionKey);
                return;
            }

            this.expandedHiddenAccessKeys = [
                ...this.expandedHiddenAccessKeys,
                permissionKey,
            ];
        },

        ownerLabel(ownerKey) {
            if (ownerKey === null) {
                return this.$t('sw-users-permissions.roles.grid.sharedData');
            }

            return this.$t(`sw-privileges.permissions.${ownerKey}.label`);
        },

        operationsLabel(operations) {
            return operations.map((operation) => this.$t(`sw-privileges.permissionType.${operation}`)).join(', ');
        },

        sourceLabel(adminPrivilegeKey) {
            const [key, role] = adminPrivilegeKey.split('.');
            const mapping = this.privileges.getPrivilegesMappings().find((privilegeMapping) => {
                return privilegeMapping.key === key && role in privilegeMapping.roles;
            });

            if (mapping?.category === 'additional_permissions') {
                return this.$t(`sw-privileges.additional_permissions.${key}.${role}`);
            }

            return this.$t(`sw-privileges.permissions.${key}.label`);
        },

        sourceLabels(adminPrivilegeKeys) {
            return [...new Set(adminPrivilegeKeys.map((adminPrivilegeKey) => this.sourceLabel(adminPrivilegeKey)))];
        },

        grantedViaNotes(permissionKey) {
            const access = this.indirectAccessByOwner[permissionKey];

            if (!access) {
                return [];
            }

            const sourceKeysByOperation = access.entries.reduce((byOperation, entry) => {
                byOperation[entry.operation] = [
                    ...(byOperation[entry.operation] ?? []),
                    ...entry.sourceKeys,
                ];

                return byOperation;
            }, {});

            const sourcesByOperation = [
                'read',
                'update',
                'create',
                'delete',
                ...Object.keys(sourceKeysByOperation),
            ]
                .filter((operation, index, operations) => operations.indexOf(operation) === index)
                .filter((operation) => sourceKeysByOperation[operation])
                .map((operation) => ({
                    operation,
                    modules: this.sourceLabels(sourceKeysByOperation[operation]).sort().join(', '),
                }));

            // Operations reached through the same modules share one note
            const notes = sourcesByOperation.reduce((byModules, { operation, modules }) => {
                byModules.set(modules, [...(byModules.get(modules) ?? []), operation]);

                return byModules;
            }, new Map());

            return [...notes.entries()].map(([modules, operations]) => ({
                key: operations.join('-'),
                text: this.$t('sw-users-permissions.roles.grid.grantedViaEntry', {
                    operation: this.operationsLabel(operations),
                    modules,
                }),
            }));
        },

        grantedViaTooltip(permissionKey) {
            const notes = this.grantedViaNotes(permissionKey).map((note) => note.text);

            return `${this.$t('sw-users-permissions.roles.grid.grantedViaTitle')} ${notes.join('; ')}`;
        },

        onShowDetailedPrivileges() {
            this.$router.push({
                name: 'sw.users.permissions.role.detail.detailed-privileges',
                params: { id: this.$route.params.id },
            });
        },

        parentLabel(parentValue) {
            const key = parentValue || 'other';
            const snippetKey = Object.hasOwn(this.parentSnippetKeys, key) ? this.parentSnippetKeys[key] : key;

            return this.$t(`sw-privileges.permissions.parents.${snippetKey}`);
        },

        compareParents(a, b) {
            const keyA = a || 'other';
            const keyB = b || 'other';

            const rank = (key) => {
                if (key === 'other') {
                    return this.parentOrder.length + 1;
                }

                const index = this.parentOrder.indexOf(key);

                return index === -1 ? this.parentOrder.length : index;
            };

            const rankDiff = rank(keyA) - rank(keyB);
            if (rankDiff !== 0) {
                return rankDiff;
            }

            return this.parentLabel(a).localeCompare(this.parentLabel(b));
        },

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
            this.role.privileges = this.role.privileges.filter((privilege) => {
                return privilege !== identifier;
            });
        },

        isPermissionSelected(permissionKey, permissionRole) {
            return this.role.privileges.some((privilege) => {
                return privilege === `${permissionKey}.${permissionRole}`;
            });
        },

        isPermissionDisabled(permissionKey, permissionRole) {
            return this.usedDependencies.includes(`${permissionKey}.${permissionRole}`);
        },

        privilegeTooltip(permissionKey, permissionRole) {
            const operationMap = {
                viewer: 'read',
                editor: 'update',
                creator: 'create',
                deleter: 'delete',
            };
            return `${permissionKey}:${operationMap[permissionRole] ?? permissionRole}`;
        },

        parentRoleTooltip(parentValue, role) {
            return this.$t('sw-users-permissions.roles.grid.tooltipParentRole', {
                role: this.$t(`sw-privileges.roles.${role}`),
                parent: this.parentLabel(parentValue),
            });
        },

        allRolesLabel() {
            return this.roles.map((r) => this.$t(`sw-privileges.roles.${r}`)).join(', ');
        },

        parentAllTooltip(parentValue) {
            return this.$t('sw-users-permissions.roles.grid.tooltipParentAll', {
                parent: this.parentLabel(parentValue),
                roles: this.allRolesLabel(),
            });
        },

        entityAllTooltip(permissionKey) {
            return this.$t('sw-users-permissions.roles.grid.tooltipEntityAll', {
                entity: this.$t(`sw-privileges.permissions.${permissionKey}.label`),
                roles: this.allRolesLabel(),
            });
        },

        changeAllPermissionsForKey(permissionKey) {
            const areAllSelected = this.allPermissionsForKeySelected(permissionKey);

            this.roles.forEach((role) => {
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
            const containsUnselected = this.roles.some((permissionRole) => {
                const doesExist = this.privileges.existsPrivilege(`${permissionKey}.${permissionRole}`);

                if (!doesExist) {
                    return false;
                }

                return !this.isPermissionSelected(permissionKey, permissionRole);
            });

            return !containsUnselected;
        },

        getPermissionsForParent(parentKey) {
            return this.permissions.filter((permission) => {
                return permission.parent === parentKey;
            });
        },

        areAllChildrenRolesSelected(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            const hasUnselected = permissionsForParent.some((permission) => {
                if (permission.roles[roleKey] === undefined) {
                    return false;
                }

                return !this.isPermissionSelected(permission.key, roleKey);
            });

            return !hasUnselected;
        },

        areAllChildrenWithAllRolesSelected(parentKey) {
            return this.roles.every((roleKey) => {
                return this.areAllChildrenRolesSelected(parentKey, roleKey);
            });
        },

        areSomeChildrenRolesSelected(parentKey, roleKey, ignoreMissingPrivilege = true) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            return permissionsForParent.some((permission) => {
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
            return this.roles.every((roleKey) => {
                return this.areSomeChildrenRolesSelected(parentKey, roleKey, false);
            });
        },

        isParentRoleDisabled(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            return permissionsForParent.every((permission) => {
                return this.isPermissionDisabled(permission.key, roleKey);
            });
        },

        toggleAllChildrenWithRole(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);
            const allChildrenRolesSelected = this.areAllChildrenRolesSelected(parentKey, roleKey);

            permissionsForParent.forEach((permission) => {
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

            return this.roles.forEach((roleKey) => {
                permissionsForParent.forEach((permission) => {
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
            return this.getPermissionsForParent(parentKey).some((currentRole) => {
                return currentRole.roles[roleKey] !== undefined;
            });
        },
    },
};
