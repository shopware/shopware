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
    name: 'sw-extension-file-upload',
    load: () => import('./src/sw-extension-file-upload'),
    public: {
        blocks: [
            'sw_extension_file_upload',
            'sw_extension_file_upload_content',
            'sw_extension_file_upload_button',
            'sw_extension_file_upload_form',
            'sw_extension_file_upload_confirm_modal',
            'sw_extension_file_upload_confirm_modal_content',
            'sw_extension_file_upload_confirm_modal_body',
            'sw_extension_file_upload_confirm_modal_footer',
            'sw_extension_file_upload_confirm_modal_footer_checkbox',
            'sw_extension_file_upload_confirm_modal_footer_buttons',
            'sw_extension_file_upload_confirm_modal_footer_cancel',
            'sw_extension_file_upload_confirm_modal_footer_continue',
        ],
        methods: [
            'createdComponent',
            'onClickUpload',
            'onFileInputChange',
            'handleUpload',
            'showStoreError',
            'showConfirmModal',
            'closeConfirmModal',
            'getUserConfig',
            'saveConfig',
        ],
    },
});
