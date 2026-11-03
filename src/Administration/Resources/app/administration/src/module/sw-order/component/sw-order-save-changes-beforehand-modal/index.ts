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
    name: 'sw-order-save-changes-beforehand-modal',
    load: () => import('./src/sw-order-save-changes-beforehand-modal'),
    public: {
        emits: [
            'confirm',
            'cancel',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_order_save_changes_beforehand_modal',
            'sw_order_save_changes_beforehand_modal_content',
            'sw_order_save_changes_beforehand_modal_actions',
            'sw_order_save_changes_beforehand_modal_action_cancel',
            'sw_order_save_changes_beforehand_modal_action_confirm',
        ],
        methods: [
            'onConfirm',
            'onCancel',
        ],
    },
});
