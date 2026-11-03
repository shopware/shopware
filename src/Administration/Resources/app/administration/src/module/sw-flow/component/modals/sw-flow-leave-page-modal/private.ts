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
    name: 'sw-flow-leave-page-modal',
    load: () => import('./src/sw-flow-leave-page-modal'),
    public: {
        emits: [
            'page-leave-confirm',
            'page-leave-cancel',
        ],
        blocks: [
            'sw_flow_leave_page_modal',
            'sw_flow_leave_page_modal_content',
            'sw_flow_leave_page_modal_actions',
            'sw_flow_leave_page_modal_action_cancel',
            'sw_flow_leave_page_modal_action_confirm',
        ],
        methods: [
            'onConfirm',
            'onCancel',
        ],
    },
});
