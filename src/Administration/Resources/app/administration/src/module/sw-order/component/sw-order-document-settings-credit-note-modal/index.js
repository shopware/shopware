/**
 * @sw-package after-sales
 */
import template from './sw-order-document-settings-credit-note-modal.html.twig';
import './sw-order-document-settings-credit-note-modal.scss';
import { DOCUMENT_TYPES } from '../../order.types';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['loading-document'],

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

        documentNumber: {
            get() {
                return String(this.documentConfig.documentNumber);
            },
            set(value) {
                this.documentConfig.documentNumber = value;
            },
        },

        invoices() {
            return this.order.documents.filter((document) => {
                return (
                    document.documentType.technicalName === DOCUMENT_TYPES.INVOICE ||
                    document.documentType.technicalName === DOCUMENT_TYPES.ZUGFERD_INVOICE ||
                    document.documentType.technicalName === DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE
                );
            });
        },

        invoiceNumberOptions() {
            return this.invoiceNumbers.map((item) => {
                return {
                    label: String(item),
                    value: item,
                };
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            const invoiceNumbers = this.invoices.map((item) => {
                return item.config.custom.invoiceNumber;
            });

            this.invoiceNumbers = [...new Set(invoiceNumbers)].sort();
        },

        async reserveDocumentNumber(isPreview = false) {
            const { number } = await this.numberRangeService.reserve(
                `document_${DOCUMENT_TYPES.CREDIT_NOTE}`,
                this.order.salesChannelId,
                isPreview,
            );

            return number;
        },

        async onCreateDocument(additionalAction = false) {
            this.$emit('loading-document');

            const selectedInvoice = this.invoices.find((item) => {
                return item.config.custom.invoiceNumber === this.documentConfig.custom.invoiceNumber;
            });

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                const number = await this.reserveDocumentNumber();

                this.documentConfig.custom.creditNoteNumber = number;

                if (number !== this.documentConfig.documentNumber) {
                    this.createNotificationInfo({
                        message: this.$t('sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'),
                    });
                }

                this.documentConfig.documentNumber = number;
            } else {
                this.documentConfig.custom.creditNoteNumber = this.documentConfig.documentNumber;
            }

            this.callDocumentCreate(additionalAction, selectedInvoice?.id);
        },
    },
};
