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
    name: 'sw-users-permissions-role-detail',
    load: () => import('./src/sw-users-permissions-role-detail'),
    public: {
        blocks: [
            'sw_users_permissions_role_detail',
            'sw_users_permissions_role_detail_smart_bar_header',
            'sw_users_permissions_role_detail_smart_bar_header_title',
            'sw_users_permissions_role_detail_smart_bar_actions',
            'sw_users_permissions_role_detail_smart_bar_actions_button_cancel',
            'sw_users_permissions_role_detail_smart_bar_actions_button_save',
            'sw_users_permissions_role_detail_content',
            'sw_users_permissions_role_detail_content_card_view',
            'sw_users_permissions_role_detail_content_card_view_tabs',
            'sw_users_permissions_role_detail_content_card_view_mt_tabs',
            'sw_users_permissions_role_detail_content_verify_modal',
        ],
        computed: [
            'tooltipSave',
            'tooltipCancel',
            'languageId',
            'roleRepository',
            'roleId',
            'roleDetailTabs',
        ],
        methods: [
            'createdComponent',
            'createNewRole',
            'getRole',
            'onSave',
            'saveRole',
            'updateCurrentUser',
            'onCloseConfirmPasswordModal',
            'saveFinish',
            'onCancel',
        ],
    },
});
