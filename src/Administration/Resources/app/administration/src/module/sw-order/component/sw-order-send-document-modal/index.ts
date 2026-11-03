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
    name: 'sw-order-send-document-modal',
    load: () => import('./src/sw-order-send-document-modal'),
    public: {
        props: [
            'document',
            'order',
        ],
        emits: [
            'modal-close',
            'document-sent',
        ],
        blocks: [
            'sw_order_send_document_modal',
            'sw_order_send_document_modal_info',
            'sw_order_send_document_modal_info_number',
            'sw_order_send_document_modal_info_type',
            'sw_order_send_document_modal_info_date',
            'sw_order_send_document_modal_mail_template_select',
            'sw_order_send_document_modal_customer_mail_select',
            'sw_order_send_document_modal_subject',
            'sw_order_send_document_modal_mail_content',
            'sw_order_send_document_modal_secondary_action',
            'sw_order_send_document_modal_primary_action',
        ],
        computed: [
            'truncateFilter',
            'mailTemplateRepository',
            'mailHeaderFooterRepository',
            'mailTemplateCriteria',
            'mailTemplateSendCriteria',
            'primaryActionDisabled',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'setEmailTemplateAccordingToDocumentType',
            'onMailTemplateChange',
            'onSendDocument',
            'loadTheLinksForA11y',
        ],
    },
});
