import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-deactivation-modal',
    load: () => import('./src/sw-extension-deactivation-modal'),
    public: {
        props: [
            'extensionName',
            'isLicensed',
            'isLoading',
        ],
        emits: [
            'modal-close',
            'extension-deactivate',
        ],
        blocks: [
            'sw_extension_deactivation_modal',
            'sw_extension_deactivation_modal_content',
            'sw_installed_extension_card_deactivation_content_modal_body',
            'sw_extension_deactivation_modal_footer',
            'sw_extension_deactivation_modal_footer_cancel',
            'sw_extension_deactivation_modal_footer_deactivate',
        ],
        computed: [
            'removeHint',
        ],
        methods: [
            'emitClose',
            'emitDeactivate',
        ],
    },
});
