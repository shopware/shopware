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
    name: 'sw-bulk-edit-order-documents',
    load: () => import('./src/sw-bulk-edit-order-documents'),
    public: {
        props: [
            'documents',
            'value',
        ],
        blocks: [
            'sw_bulk_edit_order_documents',
            'sw_bulk_edit_order_documents_container',
            'sw_bulk_edit_order_documents_type',
            'sw_bulk_edit_order_documents_skip_sent_documents',
        ],
        computed: [
            'documentTypeRepository',
            'documentTypeCriteria',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
