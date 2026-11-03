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
    name: 'sw-app-wrong-app-url-modal',
    load: () => import('./src/sw-app-wrong-app-url-modal'),
    public: {
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_app_wrong_app_url_modal',
        ],
        computed: [
            'isAppUrlReachable',
            'appUrl',
            'appUrlStatusCode',
            'hasAppsThatRequireAppUrl',
            'display',
            'assetFilter',
        ],
        methods: [
            'copyVerifyCommand',
            'closeModal',
            'createAlertNotification',
            'removeAlertNotification',
        ],
    },
});
