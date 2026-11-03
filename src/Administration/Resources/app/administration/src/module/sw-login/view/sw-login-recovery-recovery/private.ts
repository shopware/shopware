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
    name: 'sw-login-recovery-recovery',
    load: () => import('./src/sw-login-recovery-recovery'),
    public: {
        props: [
            'hash',
        ],
        emits: [
            'is-loading',
            'is-not-loading',
        ],
        blocks: [
            'sw_login_recovery_recovery',
            'sw_login_recovery_recovery_headline',
            'sw_login_recovery_recovery_text',
            'sw_login_recovery_recovery_form',
            'sw_login_recovery_recovery_form_new_password_field',
            'sw_login_recovery_recovery_form_password_confirm_field',
            'sw_login_recovery_recovery_form_submit',
        ],
        methods: [
            'createdComponent',
            'validatePasswords',
            'updatePassword',
        ],
    },
});
