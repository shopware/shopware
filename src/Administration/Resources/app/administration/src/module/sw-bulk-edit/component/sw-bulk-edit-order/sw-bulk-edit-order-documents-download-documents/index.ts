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
    name: 'sw-bulk-edit-order-documents-download-documents',
    load: () => import('./src/sw-bulk-edit-order-documents-download-documents'),
    public: {
        blocks: [
            'sw_bulk_edit_order_documents_download_documents',
            'sw_bulk_edit_order_documents_download_documents_content',
        ],
        computed: [
            'documentTypeRepository',
            'documentTypeCriteria',
            'documentTypes',
        ],
        methods: [
            'createdComponent',
            'getDocumentTypes',
        ],
    },
});
