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
    name: 'sw-login-recovery',
    load: () => import('./src/sw-login-recovery'),
    public: {
        emits: [
            'is-loading',
            'is-not-loading',
        ],
        blocks: [
            'sw_login_recovery',
            'sw_login_recovery_headline',
            'sw_login_recovery_text',
            'sw_login_recovery_alert',
            'sw_login_recovery_link_expired',
            'sw_login_recovery_form',
            'sw_login_recovery_form_email_field',
            'sw_login_recovery_form_submit',
        ],
        computed: [
            'isRateLimited',
            'showLinkExpiredError',
        ],
        methods: [
            'mountedComponent',
            'checkEmailIsValid',
            'debouncedEmailValidation',
            'sendRecoveryMail',
            'handleRateLimitError',
            'displayRecoveryInfo',
        ],
    },
});
