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
    name: 'sw-login-login',
    load: () => import('./src/sw-login-login'),
    public: {
        emits: [
            'is-loading',
            'is-not-loading',
            'login-success',
            'login-error',
            'config-loaded',
        ],
        blocks: [
            'sw_login_login',
            'sw_login_login_form_headline',
            'sw_login_login_alert',
            'sw_login_login_sso',
            'sw_login_login_sso_button',
            'sw_login_login_sso_divider',
            'sw_login_login_user_field',
            'sw_login_login_password_field',
            'sw_login_login_error',
            'sw_login_login_submit',
            'sw_login_login_submit_button',
        ],
        computed: [
            'showLoginAlert',
        ],
        methods: [
            '_reloadPage',
            '_navigateTo',
            'createdComponent',
            'doSsoForwarding',
            'loginUserWithPassword',
            'handleLoginSuccess',
            'forwardLogin',
            'handleLoginError',
            'showLoginErrorFromResponse',
        ],
    },
});
