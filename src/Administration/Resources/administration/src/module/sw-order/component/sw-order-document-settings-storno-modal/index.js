import { Component } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-order-document-settings-storno-modal.html.twig';

Component.extend('sw-order-document-settings-storno-modal', 'sw-order-document-settings-modal', {
    template,

    data() {
        return {
            documentConfig: {
                custom: {
                    stornoNumber: '',
                    invoiceNumber: ''
                },
                documentNumber: 0,
                documentComment: '',
                documentDate: {}
            },
            invoiceNumbers: []
        };
    },
    created() {
        this.createdComponent();
    },

    props: {
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        currentDocumentType: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    computed: {

    },

    methods: {
        createdComponent() {
            this.numberRangeService.reserve(
                `document_${this.currentDocumentType.technicalName}`,
                this.order.salesChannelId,
                true
            ).then((response) => {
                this.documentConfig.documentNumber = response.number;
                this.documentConfig.documentNumberPreview = this.documentConfig.documentNumber;
                this.documentConfig.documentDate = new Date();
            });
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
                    this.documentConfig.custom.stornoNumber = response.number;
                });
            } else {
                this.documentConfig.custom.stornoNumber = this.documentConfig.documentNumber;
            }
            this.$emit('document-modal-create-document', this.documentConfig, mode);
        },
        onPreview() {
            this.documentConfig.custom.stornoNumber = this.documentConfig.documentNumber;
            this.$super.onPreview();
        },
        onSelectInvoice(selected) {
            this.documentConfig.custom.invoiceNumber = selected;
        }
    }
});
