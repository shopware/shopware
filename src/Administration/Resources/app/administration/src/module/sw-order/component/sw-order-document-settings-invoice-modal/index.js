import template from './sw-order-document-settings-invoice-modal.html.twig';

const { Component } = Shopware;

Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        onCreateDocument(additionalAction = false) {
            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false
                ).then((response) => {
                    this.documentConfig.custom.invoiceNumber = response.number;
                    this.callDocumentCreate(additionalAction);
                });
            } else {
                this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
                this.callDocumentCreate(additionalAction);
            }
        },

        onPreview() {
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            this.$super('onPreview');
        }
    }
});
