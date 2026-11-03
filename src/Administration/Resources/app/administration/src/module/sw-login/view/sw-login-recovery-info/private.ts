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
    name: 'sw-login-recovery-info',
    load: () => import('./src/sw-login-recovery-info'),
    public: {
        emits: [
            'is-not-loading',
        ],
        blocks: [
            'sw_login_recovery_info',
            'sw_login_recovery_info_info',
            'sw_login_recovery_info_icon',
            'sw_login_recovery_info_headline',
            'sw_login_recovery_info_text',
        ],
        computed: [
            'email',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
