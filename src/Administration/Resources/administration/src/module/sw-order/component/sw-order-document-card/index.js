import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-order-document-card.html.twig';
import '../sw-order-document-settings-invoice-modal/';
import '../sw-order-document-settings-storno-modal/';
import '../sw-order-document-settings-delivery-note-modal/';
import '../sw-order-document-settings-credit-note-modal/';
import '../sw-order-document-settings-modal/';
import './sw-order-document.card.scss';

Component.register('sw-order-document-card', {
    template,

    inject: ['documentService', 'numberRangeService'],

    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            documentLoading: false,
            documents: [],
            documentTypes: null,
            showModal: false,
            currentDocumentType: null,
            documentNumber: null,
            documentComment: '',
            term: ''
        };
    },
    props: {
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        isLoading: {
            type: Boolean,
            required: true,
            default() {
                return false;
            }
        }
    },
    computed: {
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
    methods: {
        getList() {
            this.documentLoading = true;

            // todo on create component
            const typePromise = this.documentTypeStore.getList(
                { page: 1, limit: 100, sortBy: 'name' }
            ).then((response) => {
                this.documentTypes = response.items;
            });


            const params = this.getListingParams();
            params.sortBy = 'createdAt';
            params.sortDirection = 'DESC';
            params.term = this.term;

            const documentPromise = this.documentStore.getList(params).then((response) => {
                this.total = response.total;
                this.documents = response.items;
            });

            Promise.all([typePromise, documentPromise]).then(() => {
                this.documentLoading = false;
            });
        },
        onSearchTermChange(searchTerm) {
            this.term = searchTerm;
            this.getList();
        },
        createDocument(orderId, documentType, params) {
            const technicalName = this.currentDocumentType.technicalName;
            return this.numberRangeService.reserve(`document_${technicalName}`, this.order.salesChannelId).then((response) => {
                params.documentNumber = response.number;
                return this.documentService.createDocument(orderId, documentType, params);
            });
        },
        onCancelModal() {
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
                this.createDocument(this.order.id, this.currentDocumentType.id, params).then((response) => {
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
                    this.currentDocumentType.id, config
                ),
                '_blank'
            );
        }
    }
});
