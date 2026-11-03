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
    name: 'sw-flow-event-change-confirm-modal',
    load: () => import('./src/sw-flow-event-change-confirm-modal'),
    public: {
        emits: [
            'modal-confirm',
            'modal-close',
        ],
        blocks: [
            'sw_flow_event_change_confirm_modal',
            'sw_flow_event_change_confirm_modal_header',
            'sw_flow_event_change_confirm_modal_text_confirmation',
            'sw_flow_event_change_confirm_footer_cancel_button',
            'sw_flow_event_change_confirm_footer_save_button',
        ],
        methods: [
            'onConfirm',
            'onClose',
        ],
    },
});
