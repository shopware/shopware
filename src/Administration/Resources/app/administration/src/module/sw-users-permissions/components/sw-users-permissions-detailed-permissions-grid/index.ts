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
    name: 'sw-users-permissions-detailed-permissions-grid',
    load: () => import('./src/sw-users-permissions-detailed-permissions-grid'),
    public: {
        props: [
            'role',
            'isLoading',
            'disabled',
            'detailedPrivileges',
        ],
        blocks: [
            'sw_users_permissions_detailed_permissions_grid',
            'sw_users_permissions_detailed_permissions_grid_header',
            'sw_users_permissions_detailed_permissions_grid_header_title',
            'sw_users_permissions_detailed_permissions_grid_header_roles',
            'sw_users_permissions_detailed_permissions_grid_header_roles_name',
            'sw_users_permissions_detailed_permissions_grid_permissions',
            'sw_users_permissions_detailed_permissions_grid_permissions_title',
            'sw_users_permissions_detailed_permissions_grid_permissions_roles',
            'sw_users_permissions_detailed_permissions_grid_permissions_roles_field',
        ],
        computed: [
            'allEntities',
            'allGeneralSelectedPrivileges',
            'permissionTypes',
        ],
        methods: [
            'isEntitySelected',
            'isEntityDisabled',
            'changePermissionForEntity',
        ],
    },
});
