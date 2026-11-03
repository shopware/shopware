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
    name: 'sw-login',
    load: () => import('./src/sw-login'),
    public: {
        props: [
            'hash',
        ],
        blocks: [
            'sw_login',
            'sw_login_container',
            'sw_login_logo',
            'sw_login_form',
            'sw_login_view',
            'sw_login_loader',
            'sw_login_back_link',
            'sw_login_forgot_password_link',
        ],
        computed: [
            'title',
            'assetFilter',
            'showBackToLoginLink',
            'showForgotPasswordLink',
        ],
        methods: [
            '_reloadPage',
            'setLoading',
            'setLoginConfig',
            'loginError',
            'loginSuccess',
        ],
    },
});
