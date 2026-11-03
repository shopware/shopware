import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-state-change-modal-attach-documents',
    load: () => import('./src/sw-order-state-change-modal-attach-documents'),
    public: {
        props: [
            'order',
            'isLoading',
        ],
        emits: [
            'on-confirm',
        ],
        blocks: [
            'sw_order_state_change_modal_attach_documents',
            'sw_order_state_change_modal_attach_documents_documents_grid',
            'sw_order_state_change_modal_attach_documents_internal_comment',
            'sw_order_state_change_modal_attach_documents_button_wrapper',
            'sw_order_state_change_modal_attach_documents_send_mail_switch',
            'sw_order_state_change_modal_attach_documents_button',
        ],
        methods: [
            'loadSendMail',
            'onConfirm',
        ],
    },
});
