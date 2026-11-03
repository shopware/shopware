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
    name: 'sw-users-permissions-role-view-detailed',
    load: () => import('./src/sw-users-permissions-role-view-detailed'),
    public: {
        props: [
            'role',
            'detailedPrivileges',
            'isLoading',
        ],
        blocks: [
            'sw_users_permissions_role_role_view_general_card_view_additional_permissions',
        ],
    },
});
