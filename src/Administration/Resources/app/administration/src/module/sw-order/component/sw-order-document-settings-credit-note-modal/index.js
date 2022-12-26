import template from './sw-order-document-settings-credit-note-modal.html.twig';
import './sw-order-document-settings-credit-note-modal.scss';

/**
 * @package customer-order
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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

            const invoiceNumbers = this.order.documents
                .filter(document => {
                    return document.documentType.technicalName === 'invoice';
                })
                .map(item => {
                    return item.config.custom.invoiceNumber;
                });

            this.invoiceNumbers = [...new Set(invoiceNumbers)].sort();
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
};
