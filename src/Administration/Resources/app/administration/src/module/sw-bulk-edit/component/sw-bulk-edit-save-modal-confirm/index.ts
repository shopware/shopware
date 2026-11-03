import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-save-modal-confirm',
    load: () => import('./src/sw-bulk-edit-save-modal-confirm'),
    public: {
        props: [
            'itemTotal',
            'bulkEditData',
        ],
        emits: [
            'title-set',
            'buttons-update',
        ],
        blocks: [
            'sw_bulk_edit_save_modal_confirm',
            'sw_bulk_edit_save_modal_confirm_trigger_flows',
            'sw_bulk_edit_save_modal_confirm_trigger_flows_switch',
            'sw_bulk_edit_save_modal_confirm_trigger_flows_alert',
        ],
        computed: [
            'isFlowTriggered',
            'triggeredFlows',
            'hasDocumentDeletionSelected',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'updateButtons',
        ],
    },
});
