import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-user-sso-access-key-create-modal',
    load: () => import('./src/sw-user-sso-access-key-create-modal'),
    public: {
        props: [
            'isLoading',
            'isOpen',
            'accessKey',
            'secretAccessKey',
            'mode',
        ],
        emits: [
            'access-key-modal-create:cancel',
            'access-key-modal-create:save',
            'access-key-modal-create:generate',
        ],
        blocks: [
            'sw_settings_sso_user_create_access_key_modal',
            'sw_settings_sso_user_create_access_key_modal_inner',
            'sw_settings_sso_user_create_access_key_modal_inner_field_access_key',
            'sw_settings_sso_user_create_access_key_modal_inner_field_secret_access_key_field',
            'sw_settings_sso_user_create_access_key_modal_inner_help_text',
            'sw_settings_sso_user_create_access_key_modal_inner_generate_new_key_button',
            'sw_settings_sso_user_create_access_key_modal_inner_footer',
            'sw_settings_sso_user_create_access_key_modal_inner_footer_cancel',
            'sw_settings_sso_user_create_access_key_modal_inner_footer_save',
        ],
        methods: [
            'onCancel',
            'onSave',
            'onGenerateNewAccessKey',
        ],
    },
});
