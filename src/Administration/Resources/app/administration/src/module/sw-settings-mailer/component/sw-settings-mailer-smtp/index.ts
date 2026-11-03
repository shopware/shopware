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
    name: 'sw-settings-mailer-smtp',
    load: () => import('./src/sw-settings-mailer-smtp'),
    public: {
        props: [
            'mailerSettings',
            'hostError',
            'portError',
        ],
        emits: [
            'host-changed',
            'port-changed',
        ],
        blocks: [
            'sw_settings_mailer_smtp',
            'sw_settings_mailer_smtp_host',
            'sw_settings_mailer_smtp_port',
            'sw_settings_mailer_smtp_username',
            'sw_settings_mailer_smtp_password',
            'sw_settings_mailer_smtp_oauth_url',
            'sw_settings_mailer_smtp_oauth_scope',
            'sw_settings_mailer_smtp_client_id',
            'sw_settings_mailer_smtp_client_secret',
            'sw_settings_mailer_smtp_encryption',
            'sw_settings_mailer_smtp_sender_address',
            'sw_settings_mailer_smtp_delivery_address',
            'sw_settings_mailer_smtp_disable_delivery',
        ],
        computed: [
            'isOauth',
            'encryptionOptions',
        ],
    },
});
