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
    name: 'sw-users-permissions-additional-permissions',
    load: () => import('./src/sw-users-permissions-additional-permissions'),
    public: {
        props: [
            'role',
            'isLoading',
            'disabled',
        ],
        blocks: [
            'sw_users_permissions_additional_permissions',
            'sw_users_permissions_additional_permissions_additional_privileges',
            'sw_users_permissions_additional_permissions_additional_privileges_headline',
            'sw_users_permissions_additional_permissions_additional_privileges_headline_content',
            'sw_users_permissions_additional_permissions_additional_privileges_switches',
            'sw_users_permissions_additional_permissions_additional_privileges_switches_content',
            'sw_users_permissions_additional_permissions_additional_privileges_switches_content_switch',
            'sw_users_permissions_additional_permissions_app_privileges',
            'sw_users_permissions_additional_permissions_app_privileges_headline',
            'sw_users_permissions_additional_permissions_app_privileges_headline_content',
            'sw_users_permissions_additional_permissions_app_privileges_switches',
            'sw_users_permissions_additional_permissions_app_privileges_switches_content',
            'sw_users_permissions_additional_permissions_app_privileges_switches_content_switch',
        ],
        computed: [
            'additionalPermissions',
            'appPermissions',
        ],
        methods: [
            'isPrivilegeSelected',
            'onSelectPrivilege',
            'changeAllAppPermissionsForKey',
        ],
    },
});
