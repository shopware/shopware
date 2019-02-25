import { Component } from 'src/core/shopware';
import template from './sw-order-document-settings-invoice-modal.html.twig';

Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', {
    template,

    data() {
        return {

        };
    },
    created() {
        this.createdComponent();
    },

    props: {

    },

    methods: {
        onCreateDocument(mode = false) {
            if (this.documentConfig.documentNumberPreview === this.documentConfig.custom.invoiceNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false
                ).then((response) => {
                    this.documentConfig.custom.invoiceNumber = response.number;
                });
            } else {
                this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            }
            this.$emit('document-modal-create-document', this.documentConfig, mode);
        }
    }
});
