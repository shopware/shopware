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
    name: 'sw-profile-index-general',
    load: () => import('./src/sw-profile-index-general'),
    public: {
        props: [
            'user',
            'languages',
            'newPassword',
            'newPasswordConfirm',
            'avatarMediaItem',
            'isUserLoading',
            'languageId',
            'isDisabled',
            'userRepository',
            'timezoneOptions',
            'userTheme',
            'userModuleIconColors',
        ],
        emits: [
            'new-password-change',
            'new-password-confirm-change',
            'user-theme-change',
            'user-module-icon-colors-change',
            'media-upload',
            'media-remove',
            'media-open',
        ],
        blocks: [
            'sw_profile_index_general',
            'sw_profile_index_general_information',
            'sw_profile_index_general_information_firstname',
            'sw_profile_index_general_information_lastname',
            'sw_profile_index_general_information_username',
            'sw_profile_index_general_information_email',
            'sw_profile_index_general_image',
            'sw_profile_index_general_image_content',
            'sw_profile_index_general_user_interface',
            'sw_profile_index_general_information_language',
            'sw_profile_index_general_information_timezone',
            'sw_profile_index_general_information_theme',
            'sw_profile_index_general_information_module_icon_colors',
            'sw_profile_index_general_password',
            'sw_profile_index_general_password_new_password',
            'sw_profile_index_general_password_new_password_confirm',
        ],
        computed: [
            'computedNewPassword',
            'computedNewPasswordConfirm',
            'localeOptions',
            'computedUserTheme',
            'moduleIconColorsOptions',
            'computedUserModuleIconColors',
        ],
        methods: [
            'onUploadMedia',
            'onDropMedia',
            'onRemoveMedia',
            'onOpenMedia',
        ],
    },
});
