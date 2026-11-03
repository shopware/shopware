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
    name: 'sw-profile-index',
    load: () => import('./src/sw-profile-index'),
    public: {
        blocks: [
            'sw_profile_index',
            'sw_profile_index_smart_bar_back',
            'sw_profile_index_headline',
            'sw_profile_index_actions',
            'sw_profile_index_tabs',
            'sw_profile_index_mt_tabs',
            'sw_profile_index_tabs_item_general',
            'sw_profile_index_tabs_item_search_preferences',
            'sw_profile_index_router_view',
            'sw_profile_index_content_confirm_password_modal',
            'sw_profile_index_media_upload_actions_media_modal',
        ],
        computed: [
            'userTheme',
            'minSearchTermLength',
            'searchPreferences',
            'userSearchPreferences',
            'isDisabled',
            'userRepository',
            'languageRepository',
            'localeRepository',
            'mediaRepository',
            'languageId',
            'profileTabs',
        ],
        methods: [
            'createdComponent',
            'beforeMountComponent',
            'loadLanguages',
            'getUserData',
            'resetGeneralData',
            'saveFinish',
            'onSave',
            'checkEmail',
            'checkPassword',
            'createErrorMessage',
            'saveUser',
            'updateCurrentUser',
            'loadMediaItem',
            'setMediaItem',
            'onDropMedia',
            'onCloseConfirmPasswordModal',
            'onUnlinkAvatar',
            'openMediaModal',
            'handleUserSaveError',
            'onChangeNewPassword',
            'onChangeNewPasswordConfirm',
            'onChangeUserTheme',
            'onChangeUserModuleIconColors',
            'saveUserTheme',
            'saveUserModuleIconColors',
            'onMediaSelectionChange',
            'getMediaDefaultFolderId',
            'saveMinSearchTermLength',
            'saveUserSearchPreferences',
            'onVerifyPasswordFinished',
        ],
    },
});
