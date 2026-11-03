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
    name: 'sw-settings-login-registration',
    load: () => import('./src/sw-settings-login-registration'),
    public: {
        blocks: [
            'sw_settings_login_registration_index',
            'sw_settings_login_registration_search_bar',
            'sw_settings_login_registration_smart_bar_header',
            'sw_settings_login_registration_smart_bar_header_title',
            'sw_settings_login_registration_smart_bar_header_title_text',
            'sw_settings_login_registration_smart_bar_header_amount',
            'sw_settings_login_registration_smart_bar_actions',
            'sw_settings_login_registration_actions_save',
            'sw_settings_login_registration_content',
            'sw_setting_login_registration_system_config',
            'sw_setting_login_registration_system_wide',
        ],
        computed: [
            'systemConfigLoading',
        ],
        methods: [
            'saveFinish',
            'onSave',
            'onLoginRegistrationLoadingChanged',
            'onSystemWideLoadingChanged',
        ],
    },
});
