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
    name: 'sw-order-leave-page-modal',
    load: () => import('./src/sw-order-leave-page-modal'),
    public: {
        emits: [
            'page-leave-confirm',
            'page-leave-cancel',
        ],
        blocks: [
            'sw_order_leave_page_modal',
            'sw_order_leave_page_modal_content',
            'sw_order_leave_page_modal_actions',
            'sw_order_leave_page_modal_action_cancel',
            'sw_order_leave_page_modal_action_confirm',
        ],
        methods: [
            'onConfirm',
            'onCancel',
        ],
    },
});
