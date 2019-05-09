import { Component } from 'src/core/shopware';
import template from './sw-order-document-settings-delivery-note-modal.html.twig';

Component.extend('sw-order-document-settings-delivery-note-modal', 'sw-order-document-settings-modal', {
    template,
    data() {
        return {
            documentConfig: {
                custom: {
                    deliveryDate: (new Date()).toISOString(),
                    deliveryNoteDate: (new Date()).toISOString()
                },
                documentNumber: 0,
                documentComment: '',
                documentDate: ''
            }
        };
    },
    created() {
        this.createdComponent();
    },
    methods: {
        onCreateDocument(additionalAction = false) {
            if (this.documentConfig.documentNumberPreview === this.documentConfig.documentNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false
                ).then((response) => {
                    this.documentConfig.custom.deliveryNoteNumber = response.number;
                    this.$emit('document-modal-create-document', this.documentConfig, additionalAction);
                });
            } else {
                this.documentConfig.custom.deliveryNoteNumber = this.documentConfig.documentNumber;
                this.$emit('document-modal-create-document', this.documentConfig, additionalAction);
            }
        },
        onPreview() {
            this.documentConfig.custom.deliveryNoteNumber = this.documentConfig.documentNumber;
            this.$super.onPreview();
        }
    }
});
