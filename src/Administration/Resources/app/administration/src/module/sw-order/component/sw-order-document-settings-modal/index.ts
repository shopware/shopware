import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-document-settings-modal',
    load: () => import('./src/sw-order-document-settings-modal'),
    public: {
        props: [
            'order',
            'currentDocumentType',
            'isLoadingDocument',
            'isLoadingPreview',
        ],
        emits: [
            'loading-document',
            'document-create',
            'preview-show',
            'page-leave-confirm',
            'page-leave',
        ],
        blocks: [
            'sw_order_document_settings_modal',
            'sw_order_document_settings_modal_form_first_row',
            'sw_order_document_settings_modal_form_document_number',
            'sw_order_document_settings_modal_form_document_date',
            'sw_order_document_settings_modal_form_file_upload',
            'sw_order_document_settings_modal_form_file_upload_toggle',
            'sw_order_document_settings_modal_form_file_upload_input',
            'sw_order_document_settings_modal_media_modal',
            'sw_order_document_settings_modal_form_additional_content',
            'sw_order_document_settings_modal_form_document_comment',
            'sw_order_document_settings_modal_form_document_footer',
            'sw_order_document_settings_modal_form_document_footer_preview',
            'sw_order_document_settings_modal_form_document_footer_cancel',
            'sw_order_document_settings_modal_form_document_footer_split_button',
            'sw_order_document_settings_modal_form_document_footer_split_button_create',
            'sw_order_document_settings_modal_form_document_footer_split_button_context',
            'sw_order_document_settings_modal_form_document_footer_split_button_context_arrow',
            'sw_order_document_settings_modal_form_document_footer_split_button_context_create_send',
            'sw_order_document_settings_modal_form_document_footer_split_button_context_create_download',
        ],
        computed: [
            'invalidInput',
            'documentNumberErrorMessage',
            'documentPreconditionsFulfilled',
            'modalTitle',
            'mediaRepository',
            'htmlPreviewDisabled',
            'documentNumber',
        ],
        methods: [
            'createdComponent',
            'onCreateDocument',
            'callDocumentCreate',
            'reserveDocumentNumber',
            'addAdditionalInformationToDocument',
            'onPreview',
            'onConfirm',
            'onCancel',
            'openMediaModal',
            'closeMediaModal',
            'onAddMediaFromLibrary',
            'successfulUploadFromUrl',
            'validateFile',
            'removeCustomDocument',
            'onAddDocument',
        ],
    },
});
