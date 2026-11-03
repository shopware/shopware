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
    name: 'sw-bulk-edit-save-modal-success',
    load: () => import('./src/sw-bulk-edit-save-modal-success'),
    public: {
        emits: [
            'title-set',
            'buttons-update',
        ],
        blocks: [
            'sw_bulk_edit_save_modal_success',
            'sw_bulk_edit_save_modal_success_icon',
            'sw_bulk_edit_save_modal_error_content',
            'sw_bulk_edit_save_modal_document_generation_errors',
            'sw_bulk_edit_save_modal_success_download_document',
        ],
        computed: [
            'documentRepository',
            'orderRepository',
            'selectedIds',
            'downloadOrderDocuments',
            'latestDocumentsCriteria',
            'selectedDocumentTypes',
            'description',
            'documentGenerationResult',
            'documentGenerationFailedItems',
            'hasSkippedDocuments',
            'hasDocumentGenerationErrors',
            'hasFailedDocumentRows',
            'documentTypeLabels',
            'failedDocumentRows',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'updateButtons',
            'loadFailedOrderNumbers',
            'getLatestDocuments',
            'downloadDocument',
            'sortDocumentTypes',
            'getDocumentTypeLabel',
            'downloadDocumentGenerationResult',
            'getDocumentGenerationResultFileContent',
            'getDocumentGenerationResultFileName',
            'getDateTimeForFileName',
        ],
    },
});
