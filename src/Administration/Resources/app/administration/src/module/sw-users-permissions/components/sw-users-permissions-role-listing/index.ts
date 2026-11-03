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
    name: 'sw-users-permissions-role-listing',
    load: () => import('./src/sw-users-permissions-role-listing'),
    public: {
        emits: [
            'get-list',
        ],
        blocks: [
            'sw_users_permissions_role_listing',
            'sw_users_permissions_role_listing_toolbar',
            'sw_users_permissions_role_listing_toolbar_search_field',
            'sw_users_permissions_role_listing_toolbar_add_role_button',
            'sw_users_permissions_role_listing_verify_modal',
            'sw_users_permissions_role_listing_grid',
            'sw_users_permissions_role_listing_grid_actions',
            'sw_users_permissions_role_listing_grid_actions_edit',
            'sw_users_permissions_role_listing_grid_actions_delete',
            'sw_users_permissions_role_listing_grid_columns',
            'sw_users_permissions_role_listing_grid_columns_name',
            'sw_users_permissions_role_listing_grid_action_modals',
            'sw_users_permissions_role_listing_grid_actions_modals_modal',
            'sw_users_permissions_role_listing_grid_actions_modals_modal_delete_text',
            'sw_users_permissions_role_listing_grid_actions_modals_modal_footer',
            'sw_users_permissions_role_listing_grid_actions_modals_modal_close_delete',
            'sw_users_permissions_role_listing_grid_actions_modals_modal_confirm_delete',
            'sw_users_permissions_role_listing_grid_pagination',
            'sw_users_permissions_role_listing_empty_state',
        ],
        computed: [
            'rolesColumns',
            'roleRepository',
            'roleCriteria',
            'showListingResults',
        ],
        methods: [
            'createdComponent',
            'getList',
            'onSearch',
            'getItemToDelete',
            'onDelete',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'deleteRole',
            'onCloseConfirmPasswordModal',
        ],
    },
});
