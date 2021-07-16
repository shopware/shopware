import template from './sw-order-document-settings-credit-note-modal.html.twig';
import './sw-order-document-settings-credit-note-modal.scss';

const { Component } = Shopware;

Component.extend('sw-order-document-settings-credit-note-modal', 'sw-order-document-settings-modal', {
    template,

    data() {
        return {
            documentConfig: {
                custom: {
                    creditNoteNumber: '',
                    invoiceNumber: '',
                },
            },
            invoiceNumbers: [],
        };
    },

    computed: {
        highlightedItems() {
            const items = [];

            this.order.lineItems.forEach((lineItem) => {
                if (lineItem.type === 'credit') {
                    items.push(lineItem);
                }
            });

            return items;
        },
        documentPreconditionsFulfilled() {
            return this.highlightedItems.length !== 0 && this.documentConfig.custom.invoiceNumber;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            this.invoiceNumbers = this.order.documents.map((item) => {
                return item.config.custom.invoiceNumber;
            });
        },

        onCreateDocument(additionalAction = false) {
            this.$emit('loading-document');

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false,
                ).then((response) => {
                    this.documentConfig.custom.creditNoteNumber = response.number;
                    if (response.number !== this.documentConfig.documentNumber) {
                        this.createNotificationInfo({
                            message: this.$tc('sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'),
                        });
                    }
                    this.documentConfig.documentNumber = response.number;
                    this.callDocumentCreate(additionalAction);
                });
            } else {
                this.documentConfig.custom.creditNoteNumber = this.documentConfig.documentNumber;
                this.callDocumentCreate(additionalAction);
            }
        },
    },
});
