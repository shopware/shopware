import { Component } from 'src/core/shopware';
import template from './sw-order-document-settings-invoice-modal.html.twig';

Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', {
    template,
    created() {
        this.createdComponent();
    },
    methods: {
        onCreateDocument(additionalAction = false) {
            if (this.documentConfig.documentNumberPreview === this.documentConfig.custom.invoiceNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false
                ).then((response) => {
                    this.documentConfig.custom.invoiceNumber = response.number;
                    this.$emit('document-modal-create-document', this.documentConfig, additionalAction);
                });
            } else {
                this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
                this.$emit('document-modal-create-document', this.documentConfig, additionalAction);
            }
        },
        onPreview() {
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            this.$super.onPreview();
        }
    }
});
