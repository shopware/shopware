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
    name: 'sw-bulk-edit-document-generation-failed-list',
    load: () => import('./src/sw-bulk-edit-document-generation-failed-list'),
    public: {
        props: [
            'rows',
            'itemsPerPage',
        ],
        blocks: [
            'sw_bulk_edit_document_generation_failed_list',
            'sw_bulk_edit_document_generation_failed_list_grid',
            'sw_bulk_edit_document_generation_failed_list_grid_column_order_number',
            'sw_bulk_edit_document_generation_failed_list_grid_column_document_types',
            'sw_bulk_edit_document_generation_failed_list_grid_pagination',
        ],
        computed: [
            'columns',
            'paginatedRows',
            'showPagination',
        ],
        methods: [
            'onPageChange',
        ],
    },
});
