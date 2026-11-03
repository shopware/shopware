import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-mail-template-preview-modal',
    load: () => import('./src/sw-mail-template-preview-modal'),
    public: {
        props: [
            'mailPreview',
            'isLoading',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_mail_template_detail_preview_modal_footer',
            'sw_mail_template_detail_preview_modal_footer_cancel',
        ],
    },
});
