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
    name: 'sw-bulk-edit-save-modal-process',
    load: () => import('./src/sw-bulk-edit-save-modal-process'),
    public: {
        emits: [
            'changes-apply',
            'title-set',
            'buttons-update',
            'redirect',
        ],
        blocks: [
            'sw_bulk_edit_save_modal_process',
            'sw_bulk_edit_save_modal_process_icon',
            'sw_bulk_edit_save_modal_process_generate_document',
            'sw_bulk_edit_save_modal_process_content',
        ],
        computed: [
            'selectedIds',
            'documentTypes',
            'deleteDocumentTypes',
            'documentTypeConfigs',
            'selectedDocumentTypes',
            'createDocumentPayload',
            'documentRepository',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'updateButtons',
            'createDocuments',
            'createDocument',
            'createDocumentV2',
            'getOrderIdsWithExistingDocument',
            'getDocumentGenerationResult',
            'getFailedDocumentGenerationItems',
            'deleteDocuments',
            'truncateErrorMessage',
        ],
    },
});
