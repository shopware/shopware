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
    name: 'sw-bulk-edit-save-modal-error',
    load: () => import('./src/sw-bulk-edit-save-modal-error'),
    public: {
        emits: [
            'title-set',
            'buttons-update',
        ],
        blocks: [
            'sw_bulk_edit_save_modal_error',
            'sw_bulk_edit_save_modal_error_icon',
            'sw_bulk_edit_save_modal_error_content',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'updateButtons',
        ],
    },
});
