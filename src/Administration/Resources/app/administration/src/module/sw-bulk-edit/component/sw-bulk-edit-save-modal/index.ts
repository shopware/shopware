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
    name: 'sw-bulk-edit-save-modal',
    load: () => import('./src/sw-bulk-edit-save-modal'),
    public: {
        props: [
            'itemTotal',
            'isLoading',
            'processStatus',
            'bulkEditData',
        ],
        emits: [
            'modal-close',
            'bulk-save',
        ],
        blocks: [
            'sw_bulk_edit_save_modal',
            'sw_bulk_edit_save_modal_body',
            'sw_bulk_edit_save_modal_footer',
            'sw_bulk_edit_save_modal_footer_left',
            'sw_bulk_edit_save_modal_footer_right',
        ],
        computed: [
            'currentStep',
            'buttons',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'addEventListeners',
            'removeEventListeners',
            'beforeUnloadListener',
            'onModalClose',
            'applyChanges',
            'redirect',
            'setTitle',
            'updateButtons',
            'onButtonClick',
        ],
    },
});
