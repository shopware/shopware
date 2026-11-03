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
    name: 'sw-order-upload-document-modal',
    load: () => import('./src/sw-order-upload-document-modal'),
    public: {
        props: [
            'order',
            'documentType',
            'isLoadingDocument',
        ],
        emits: [
            'document-upload',
            'page-leave',
            'update:documentType',
        ],
        blocks: [
            'sw_order_upload_document_modal',
        ],
        computed: [
            'currentTechnicalName',
            'documentNumberErrorMessage',
            'documentTypeOptions',
            'documentFamily',
            'fileFormatOptions',
            'isModalLoading',
            'invalidInput',
            'fileAcceptTypes',
            'isStornoDocument',
            'mediaRepository',
        ],
        methods: [
            'createdComponent',
            'onDocumentTypeChange',
            'reserveDocumentNumber',
            'onUploadDocument',
            'onCancel',
            'successfulUploadFromUrl',
            'validateFile',
            'removeCustomDocument',
            'onAddDocument',
        ],
    },
});
