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
    name: 'sw-order-create-document-modal',
    load: () => import('./src/sw-order-create-document-modal'),
    public: {
        props: [
            'order',
            'documentType',
            'isLoadingDocument',
            'isLoadingPreview',
        ],
        emits: [
            'document-create',
            'page-leave',
            'preview-show',
            'update:documentType',
        ],
        blocks: [
            'sw_order_create_document_modal',
        ],
        computed: [
            'creditItems',
            'currentTechnicalName',
            'documentNumberErrorMessage',
            'referencedDocumentNumberErrorMessage',
            'documentTypeOptions',
            'documentFamily',
            'fileFormatOptions',
            'isModalLoading',
            'invalidInput',
            'referencedDocumentNumberOptions',
            'isCreditNoteDocument',
            'isDeliveryNoteDocument',
            'isStornoDocument',
            'isReferencingOtherDocument',
            'previewFileFormatOptions',
        ],
        methods: [
            'createdComponent',
            'onDocumentTypeChange',
            'reserveDocumentNumber',
            'onCreateDocument',
            'onPreview',
            'onCancel',
            'getReferencedDocumentId',
        ],
    },
});
