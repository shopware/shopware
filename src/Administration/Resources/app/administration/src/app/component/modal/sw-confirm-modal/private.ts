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
    name: 'sw-confirm-modal',
    load: () => import('./src/sw-confirm-modal'),
    public: {
        props: [
            'title',
            'text',
            'textConfirm',
            'variant',
            'type',
        ],
        emits: [
            'close',
            'cancel',
            'confirm',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_confirm_modal',
            'sw_confirm_modal_text',
            'sw_confirm_modal_text_default',
            'sw_confirm_modal_footer',
            'sw_confirm_modal_footer_cancel',
            'sw_confirm_modal_footer_confirm',
        ],
        computed: [
            'titleText',
            'descriptionText',
            'confirmText',
            'cancelText',
            'confirmButtonVariant',
        ],
    },
});
