import { Component } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import './sw-order-document-settings-credit-note-modal.scss';
import template from './sw-order-document-settings-credit-note-modal.html.twig';

Component.extend('sw-order-document-settings-credit-note-modal', 'sw-order-document-settings-modal', {
    template,

    data() {
        return {
            documentConfig: {
                custom: {
                    creditNoteNumber: '',
                    invoiceNumber: ''
                }
            },
            invoiceNumbers: []
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
            return this.highlightedItems.length !== 0;
        }
    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.$super.createdComponent();

            const criteria = CriteriaFactory.equals('documentType.technicalName', 'invoice');
            this.order.getAssociation('documents').getList(
                { page: 1, limit: 50, criteria: criteria }
            ).then((response) => {
                this.invoiceNumbers = [];
                if (response.items.length > 0) {
                    response.items.forEach((item) => {
                        this.invoiceNumbers.push(item.config.custom.invoiceNumber);
                    });
                }
            });
        },
        onCreateDocument(mode = false) {
            if (this.documentConfig.documentNumberPreview === this.documentConfig.documentNumber) {
                this.numberRangeService.reserve(
                    `document_${this.currentDocumentType.technicalName}`,
                    this.order.salesChannelId,
                    false
                ).then((response) => {
                    this.documentConfig.custom.creditNoteNumber = response.number;
                });
            } else {
                this.documentConfig.custom.creditNoteNumber = this.documentConfig.documentNumber;
            }
            this.$emit('document-modal-create-document', this.documentConfig, mode);
        }
    }
});
