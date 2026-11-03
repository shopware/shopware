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
    name: 'sw-bulk-edit-order-documents-delete-documents',
    load: () => import('./src/sw-bulk-edit-order-documents-delete-documents'),
    public: {
        blocks: [
            'sw_bulk_edit_order_documents_delete_documents',
        ],
        computed: [
            'documentTypeRepository',
            'documentTypeCriteria',
            'documentTypes',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
