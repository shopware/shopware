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
    name: 'sw-verify-user-modal',
    load: () => import('./src/sw-verify-user-modal'),
    public: {
        emits: [
            'verified',
            'close',
        ],
        blocks: [
            'sw_settings_user_detail_content_confirm_password_modal',
            'sw_settings_user_detail_content_confirm_password_modal_input__confirm_password',
            'sw_settings_user_detail_content_confirm_password_modal_footer',
            'sw_settings_user_detail_content_confirm_password_modal_actions_cancel',
            'sw_settings_user_detail_content_confirm_password_modal_actions_change',
        ],
        methods: [
            'createdComponent',
            'onSubmitConfirmPassword',
            'onCloseConfirmPasswordModal',
        ],
    },
});
