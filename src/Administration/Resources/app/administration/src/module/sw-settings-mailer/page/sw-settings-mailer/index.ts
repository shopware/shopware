import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-mailer',
    load: () => import('./src/sw-settings-mailer'),
    public: {
        blocks: [
            'sw_settings_mailer_index',
            'sw_settings_mailer_smart_bar_header',
            'sw_settings_mailer_smart_bar_header_title',
            'sw_settings_mailer_smart_bar_header_title_text',
            'sw_settings_mailer_smart_bar_actions',
            'sw_settings_mailer_actions_save',
            'sw_settings_mailer_content',
            'sw_settings_mailer_first_configuration',
            'sw_settings_mailer_first_configuration_headline',
            'sw_settings_mailer_first_configuration_description',
            'sw_settings_mailer_headline_agent',
            'sw_settings_mailer_agent_options',
            'sw_settings_mailer_smtp',
            'sw_settings_mailer_smtp_settings',
        ],
        computed: [
            'emailSendmailOptions',
            'isSmtpMode',
            'emailAgentOptions',
        ],
        methods: [
            'createdComponent',
            'loadPageContent',
            'loadMailerSettings',
            'saveMailerSettings',
            'onSaveFinish',
            'checkFirstConfiguration',
            'validateSmtpConfiguration',
            'resetSmtpHostError',
            'resetSmtpPortError',
        ],
    },
});
