import { Component } from 'src/core/shopware';
import template from './sw-order-document-settings-modal.html.twig';

Component.register('sw-order-document-settings-modal', {
    template,
    inject: ['numberRangeService'],
    props: {
        order: {
            type: Object,
            required: true
        },
        currentDocumentType: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            showModal: false,
            documentConfig: {
                custom: {},
                documentNumber: 0,
                documentComment: '',
                documentDate: {}
            },
            documentNumberPreview: false
        };
    },
    computed: {
        documentPreconditionsFulfilled() {
            // can be overwritten in extending component
            return true;
        }
    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.numberRangeService.reserve(
                `document_${this.currentDocumentType.technicalName}`,
                this.order.salesChannelId,
                true
            ).then((response) => {
                this.documentConfig.documentNumber = response.number;
                this.documentNumberPreview = this.documentConfig.documentNumber;
                this.documentConfig.documentDate = new Date();
            });
        },
        onCreateDocument(additionalAction = false) {
            this.$emit('document-modal-create-document', this.documentConfig, additionalAction);
        },
        onPreview() {
            this.$emit('document-modal-show-preview', this.documentConfig);
        },
        onConfirm() {
            this.$emit('leave-page-confirm');
        },
        onCancel() {
            this.$emit('document-modal-leave-page');
        }
    }
});
