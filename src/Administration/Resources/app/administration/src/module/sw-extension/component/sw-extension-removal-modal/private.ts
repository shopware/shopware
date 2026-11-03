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
    name: 'sw-extension-removal-modal',
    load: () => import('./src/sw-extension-removal-modal'),
    public: {
        props: [
            'extensionName',
            'isLicensed',
            'isLoading',
        ],
        emits: [
            'modal-close',
            'remove-extension',
        ],
        blocks: [
            'sw_extension_removal_modal',
            'sw_extension_removal_modal_content',
            'sw_installed_extension_card_removal_content_modal_body',
            'sw_extension_removal_modal_footer',
            'sw_extension_removal_modal_footer_cancel',
            'sw_extension_removal_modal_footer_deactivate',
        ],
        computed: [
            'title',
            'alert',
            'btnLabel',
        ],
        methods: [
            'emitClose',
            'emitRemoval',
        ],
    },
});
