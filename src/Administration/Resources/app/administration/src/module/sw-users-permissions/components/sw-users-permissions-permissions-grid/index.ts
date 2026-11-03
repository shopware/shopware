import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-users-permissions-permissions-grid',
    load: () => import('./src/sw-users-permissions-permissions-grid'),
    public: {
        props: [
            'role',
            'isLoading',
            'disabled',
        ],
        blocks: [
            'sw_users_permissions_permissions_grid',
            'sw_users_permissions_permissions_grid_header',
            'sw_users_permissions_permissions_grid_header_title',
            'sw_users_permissions_permissions_grid_header_title_content',
            'sw_users_permissions_permissions_grid_header_roles',
            'sw_users_permissions_permissions_grid_header_roles_name',
            'sw_users_permissions_permissions_grid_header_all_roles',
            'sw_users_permissions_permissions_grid_header_all_roles_name',
            'sw_users_permissions_permissions_grid_permissions',
            'sw_users_permissions_permissions_grid_parent_title',
            'sw_users_permissions_permissions_grid_parent_title_content',
            'sw_users_permissions_permissions_grid_parent_roles',
            'sw_users_permissions_permissions_grid_parent_roles_field',
            'sw_users_permissions_permissions_grid_parent_all_roles',
            'sw_users_permissions_permissions_grid_parent_all_roles_field',
            'sw_users_permissions_permissions_grid_permissions_title',
            'sw_users_permissions_permissions_grid_permissions_title_content',
            'sw_users_permissions_permissions_grid_permissions_roles',
            'sw_users_permissions_permissions_grid_permissions_roles_field',
            'sw_users_permissions_permissions_grid_permissions_all_roles',
            'sw_users_permissions_permissions_grid_permissions_all_roles_field',
        ],
        computed: [
            'permissionsWithParents',
            'permissions',
            'parents',
            'usedDependencies',
            'roles',
            'parentOrder',
            'parentSnippetKeys',
        ],
        methods: [
            'parentLabel',
            'compareParents',
            'changePermission',
            'addPermission',
            'addDependenciesForRole',
            'removePermission',
            'isPermissionSelected',
            'isPermissionDisabled',
            'privilegeTooltip',
            'parentRoleTooltip',
            'allRolesLabel',
            'parentAllTooltip',
            'entityAllTooltip',
            'changeAllPermissionsForKey',
            'allPermissionsForKeySelected',
            'getPermissionsForParent',
            'areAllChildrenRolesSelected',
            'areAllChildrenWithAllRolesSelected',
            'areSomeChildrenRolesSelected',
            'areSomeChildrenWithAllRolesSelected',
            'isParentRoleDisabled',
            'toggleAllChildrenWithRole',
            'toggleAllChildrenWithAllRoles',
            'parentRoleHasChildRoles',
        ],
    },
});
