import template from './sw-order-document-settings-invoice-modal.html.twig';

const { Component, Mixin } = Shopware;

Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        onCreateDocument(additionalAction = false) {
            this.$emit('loading-document');

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false,
                ).then((response) => {
                    this.documentConfig.custom.invoiceNumber = response.number;
                    if (response.number !== this.documentConfig.documentNumber) {
                        this.createNotificationInfo({
                            message: this.$tc('sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'),
                        });
                    }

                    this.documentConfig.documentNumber = response.number;
                    this.callDocumentCreate(additionalAction);
                });
            } else {
                this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
                this.callDocumentCreate(additionalAction);
            }
        },

        onPreview() {
            this.$emit('loading-preview');
            this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
            this.$super('onPreview');
        },
    },
});
