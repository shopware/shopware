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
    name: 'sw-users-permissions-user-listing',
    load: () => import('./src/sw-users-permissions-user-listing'),
    public: {
        emits: [
            'get-list',
        ],
        blocks: [
            'sw_settings_user_list',
            'sw_settings_user_list_smart_bar_actions',
            'sw_settings_user_list_search_bar',
            'sw_settings_user_list_actions_create_user',
            'sw_settings_user_list_content',
            'sw_settings_user_list_content_grid',
            'sw_settings_user_list_content_grid_actions',
            'sw_settings_user_list_actions_edit',
            'sw_settings_user_list_actions_delete',
            'sw_settings_user_list_column_username_preview',
            'sw_settings_user_list_column_username',
            'sw_settings_user_list_column_username_content',
            'sw_settings_user_list_column_email',
            'sw_settings_user_list_column_email_content',
            'sw_settings_user_list_column_status',
            'sw_settings_user_list_column_username_acl_roles',
            'sw_settings_user_list_grid_pagination',
            'sw_settings_user_list_delete_modal',
            'sw_settings_user_list_delete_modal_confirm_delete_text',
            'sw_settings_user_list_delete_modal_input__confirm_password',
            'sw_settings_user_list_delete_modal_footer',
            'sw_settings_user_list_delete_modal_cancel',
            'sw_settings_user_list_delete_modal_confirm',
        ],
        computed: [
            'userRepository',
            'currentUser',
            'userDetailRouterLink',
            'userCriteria',
            'userColumns',
        ],
        methods: [
            'getItemToDelete',
            'onSearch',
            'getList',
            'onDelete',
            'onUserInvited',
            'openInvitationModal',
            'closeInvitationModal',
            'invitationFailed',
            'onConfirmDelete',
            'deleteUser',
            'onCloseConfirmPasswordModal',
            'onCloseDeleteModal',
        ],
    },
});
