import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-order-document-card.html.twig';
import './sw-order-document.card.scss';
import '../sw-order-document-settings-invoice-modal/';
import '../sw-order-document-settings-storno-modal/';
import '../sw-order-document-settings-delivery-note-modal/';
import '../sw-order-document-settings-credit-note-modal/';
import '../sw-order-document-settings-modal/';

Component.register('sw-order-document-card', {
    template,

    inject: ['documentService', 'numberRangeService'],

    mixins: [Mixin.getByName('listing')],

    props: {
        order: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            documentsLoading: false,
            cardLoading: false,
            documents: [],
            documentTypes: null,
            showModal: false,
            currentDocumentType: null,
            documentNumber: null,
            documentComment: '',
            term: ''
        };
    },

    computed: {
        creditItems() {
            const items = [];

            this.order.lineItems.forEach((lineItem) => {
                if (lineItem.type === 'credit') {
                    items.push(lineItem);
                }
            });

            return items;
        },

        documentStore() {
            return this.order.getAssociation('documents');
        },

        documentTypeStore() {
            return State.getStore('document_type');
        },

        documentModal() {
            const subComponentName = this.currentDocumentType.technicalName.replace('_', '-');
            if (this.$options.components[`sw-order-document-settings-${subComponentName}-modal`]) {
                return `sw-order-document-settings-${this.currentDocumentType.technicalName.replace('_', '-')}-modal`;
            }
            return 'sw-order-document-settings-modal';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.cardLoading = true;

            this.documentTypeStore.getList(
                { page: 1, limit: 100, sortBy: 'name' }
            ).then((response) => {
                this.documentTypes = response.items;
                this.cardLoading = false;
            });
        },

        getList() {
            this.documentsLoading = true;

            const params = this.getListingParams();
            params.sortBy = 'createdAt';
            params.sortDirection = 'DESC';
            params.term = this.term;

            this.documentStore.getList(params).then((response) => {
                this.total = response.total;
                this.documents = response.items;
            }).then(() => {
                this.documentsLoading = false;
            });
        },

        documentTypeAvailable(documentType) {
            return (
                (
                    documentType.technicalName !== 'storno' &&
                    documentType.technicalName !== 'credit_note'
                ) ||
                (
                    (
                        documentType.technicalName === 'storno' ||
                        (
                            documentType.technicalName === 'credit_note' &&
                            this.creditItems.length !== 0
                        )
                    ) && this.invoiceExists()
                )
            );
        },

        invoiceExists() {
            return this.documents.some((document) => {
                return (document.documentType.technicalName === 'invoice');
            });
        },

        onSearchTermChange(searchTerm) {
            this.term = searchTerm;
            this.getList();
        },

        createDocument(orderId, documentTypeName, params) {
            return this.documentService.createDocument(orderId, documentTypeName, params).then(() => {
                this.getList();
            });
        },

        onCancelCreation() {
            this.showModal = false;
            this.currentDocumentType = null;
        },

        onPrepareDocument(documentType) {
            this.currentDocumentType = documentType;
            this.showModal = true;
        },

        onCreateDocument(params, additionalAction) {
            this.showModal = false;
            this.$nextTick().then(() => {
                this.createDocument(this.order.id, this.currentDocumentType.technicalName, params).then((response) => {
                    this.getList();

                    if (additionalAction === 'download') {
                        const docId = response.data.documentId;
                        const docLink = response.data.documentDeepLink;
                        window.open(
                            this.documentService.generateDocumentLink(docId, docLink, true), '_blank'
                        );
                    }
                });
            });
        },

        onPreview(params) {
            const config = JSON.stringify(params);
            window.open(
                this.documentService.generateDocumentPreviewLink(
                    this.order.id,
                    this.order.deepLinkCode,
                    this.currentDocumentType.technicalName, config
                ),
                '_blank'
            );
        },

        onDownload(id, deepLink) {
            window.open(this.documentService.generateDocumentLink(id, deepLink), '_blank');
        }
    }
});
