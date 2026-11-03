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
    name: 'sw-bulk-edit-modal',
    load: () => import('./src/sw-bulk-edit-modal'),
    public: {
        props: [
            'selection',
            'steps',
            'bulkGridEditColumns',
        ],
        emits: [
            'modal-close',
            'edit-items',
        ],
        slots: [
            'slot',
            'sw-bulk-edit-modal-cancel',
            'sw-bulk-edit-modal-confirm',
        ],
        blocks: [
            'sw_bulk_edit_modal',
            'sw_bulk_edit_modal_grid',
            'sw_bulk_edit_modal_grid_custom_slot',
            'sw_bulk_edit_modal_list_pagination',
            'sw_bulk_edit_modal_grid_footer',
        ],
        computed: [
            'itemCount',
            'paginateRecords',
            'getSlots',
        ],
        methods: [
            'createdComponent',
            'paginate',
            'updateBulkEditSelection',
            'editItems',
        ],
    },
});
