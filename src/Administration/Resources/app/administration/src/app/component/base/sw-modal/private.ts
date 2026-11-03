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
    name: 'sw-modal',
    load: () => import('./src/sw-modal'),
    public: {
        props: [
            'title',
            'subtitle',
            'size',
            'variant',
            'isLoading',
            'selector',
            'showHeader',
            'showFooter',
            'closable',
            'zIndex',
        ],
        emits: [
            'modal-close',
        ],
        slots: [
            'modal-header',
            'modal-title',
            'body',
            'modal-loader',
            'default',
            'modal-footer',
        ],
        blocks: [
            'sw_modal',
            'sw_modal_element',
            'sw_modal_dialog',
            'sw_modal_header',
            'sw_modal_slot_header',
            'sw_modal_close',
            'sw_modal_close_button',
            'sw_modal_close_icon',
            'sw_modal_body',
            'sw_modal_loader',
            'sw_modal_slot_default',
            'sw_modal_footer',
            'sw_modal_slot_footer',
        ],
        computed: [
            'modalClasses',
            'modalDialogClasses',
            'modalBodyClasses',
            'hasFooterSlot',
            'showHelpSidebar',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeDestroyComponent',
            'destroyedComponent',
            'setFocusToModal',
            'closeModalOnClickOutside',
            'closeModal',
            'closeModalOnEscapeKey',
        ],
    },
});
