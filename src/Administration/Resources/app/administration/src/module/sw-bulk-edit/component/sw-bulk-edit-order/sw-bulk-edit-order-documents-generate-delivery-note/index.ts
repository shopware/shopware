import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-bulk-edit-order-documents-generate-invoice,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-order-documents-generate-delivery-note',
    extends: 'sw-bulk-edit-order-documents-generate-invoice',
    load: () => import('./src/sw-bulk-edit-order-documents-generate-delivery-note'),
    public: {
        blocks: [
            'sw_bulk_edit_order_documents_generate_invoice_datepicker',
            'sw_bulk_edit_order_documents_generate_invoice',
            'sw_bulk_edit_order_documents_generate_invoice_file_formats',
            'sw_bulk_edit_order_documents_generate_invoice_textarea',
            'sw_bulk_edit_order_documents_generate_invoice_toggle_skip',
            'sw_bulk_edit_order_documents_generate_invoice_placeholder',
        ],
        computed: [
            'generateData',
            'documentTypeTechnicalName',
            'fileFormatOptions',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
