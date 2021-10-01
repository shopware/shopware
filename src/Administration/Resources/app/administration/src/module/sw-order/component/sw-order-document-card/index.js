import { DocumentEvents } from 'src/core/service/api/document.api.service';
import template from './sw-order-document-card.html.twig';
import './sw-order-document-card.scss';
import '../sw-order-document-settings-invoice-modal';
import '../sw-order-document-settings-storno-modal';
import '../sw-order-document-settings-delivery-note-modal';
import '../sw-order-document-settings-credit-note-modal';
import '../sw-order-document-settings-modal';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-document-card', {
    template,

    inject: [
        'documentService',
        'numberRangeService',
        'repositoryFactory',
        'feature',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        attachView: {
            type: Boolean,
            required: false,
            default: false,
        },
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
            term: '',
            attachment: {},
            isLoadingDocument: false,
            isLoadingPreview: false,
            showSelectDocumentTypeModal: false,
            showSendDocumentModal: false,
            sendDocument: null,
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

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        documentsEmpty() {
            return this.documents.length === 0;
        },

        documentModal() {
            const subComponentName = this.currentDocumentType.technicalName.replace(/_/g, '-');
            if (this.$options.components[`sw-order-document-settings-${subComponentName}-modal`]) {
                return `sw-order-document-settings-${subComponentName}-modal`;
            }
            return 'sw-order-document-settings-modal';
        },

        documentCardStyles() {
            return `sw-order-document-card ${this.documentsEmpty ? 'sw-order-document-card--is-empty' : ''}`;
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        documentCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            criteria.setTerm(this.term);
            criteria.addAssociation('documentType');
            criteria.addFilter(Criteria.equals('order.id', this.order.id));
            criteria.addFilter(Criteria.equals('order.versionId', this.order.versionId));

            return criteria;
        },

        getDocumentColumns() {
            const columns = [{
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: 'sw-order.documentCard.labelDate',
                allowResize: false,
                primary: true,
            }, {
                property: 'config.documentNumber',
                dataIndex: 'config.documentNumber',
                label: 'sw-order.documentCard.labelNumber',
                allowResize: false,
            }, {
                property: 'documentType.name',
                dataIndex: 'documentType.name',
                label: 'sw-order.documentCard.labelType',
                allowResize: false,
            }, {
                property: 'sent',
                dataIndex: 'sent',
                label: 'sw-order.documentCard.labelSent',
                allowResize: false,
                align: 'center',
            }];

            if (this.attachView) {
                columns.push({
                    property: 'attach',
                    dataIndex: 'attach',
                    label: 'sw-order.documentCard.labelAttach',
                    allowResize: false,
                    align: 'center',
                });
            }

            return columns;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.cardLoading = true;

            this.documentTypeRepository.search(this.documentTypeCriteria).then((response) => {
                this.documentTypes = response;
                this.cardLoading = false;
            });

            this.documentService.setListener(this.convertStoreEventToVueEvent);
        },

        convertStoreEventToVueEvent({ action, payload }) {
            if (action === DocumentEvents.DOCUMENT_FAILED) {
                let errorMessage = payload.detail;
                if (payload.code === 'DOCUMENT__NUMBER_ALREADY_EXISTS') {
                    const translationKey = 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS';
                    errorMessage = this.$tc(translationKey, 1, payload.meta.parameters || {});
                }

                this.createNotificationError({
                    message: errorMessage,
                });
            } else if (action === DocumentEvents.DOCUMENT_FINISHED) {
                this.showModal = false;
                this.$nextTick().then(() => {
                    this.getList().then(() => {
                        this.$emit('document-save');
                    });
                });
            }
        },

        getList() {
            this.documentsLoading = true;

            return this.documentRepository.search(this.documentCriteria).then((response) => {
                this.total = response.total;
                this.documents = response;
                this.documentsLoading = false;
                return Promise.resolve();
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

        createDocument(orderId, documentTypeName, params, referencedDocumentId, file) {
            return this.documentService.createDocument(
                orderId,
                documentTypeName,
                params,
                referencedDocumentId,
                {},
                {},
                file,
            );
        },

        onCancelCreation() {
            this.showModal = false;
            this.currentDocumentType = null;
        },

        onPrepareDocument(documentType) {
            if (!this.feature.isActive('FEATURE_NEXT_7530')) {
                this.currentDocumentType = documentType;
            }

            this.showModal = true;
        },

        openDocument(documentId, documentDeepLink) {
            this.documentService.getDocument(
                documentId,
                documentDeepLink,
                Shopware.Context.api,
                true,
            ).then((response) => {
                if (response.data) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(response.data);
                    link.target = '_blank';
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                }
            });
        },

        downloadDocument(documentId, documentDeepLink) {
            this.documentService.getDocument(
                documentId,
                documentDeepLink,
                Shopware.Context.api,
                true,
            ).then((response) => {
                if (response.data) {
                    const filename = response.headers['content-disposition'].split('filename=')[1];
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(response.data);
                    link.download = filename;
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                }
            });
        },

        markDocumentAsSent(documentId) {
            const document = this.documents.get(documentId);
            document.sent = true;

            this.documentRepository.save(document).then(() => {
                this.getList();
            });
        },

        markDocumentAsUnsent(documentId) {
            const document = this.documents.get(documentId);
            document.sent = false;

            this.documentRepository.save(document).then(() => {
                this.getList();
            });
        },

        onCreateDocument(params, additionalAction, referencedDocumentId = null, file = null) {
            this.isLoadingDocument = true;

            this.$nextTick().then(() => {
                return this.createDocument(
                    this.order.id,
                    this.currentDocumentType.technicalName,
                    params,
                    referencedDocumentId,
                    file,
                );
            }).then((response) => {
                if (response && additionalAction === 'download') {
                    this.downloadDocument(response.data.documentId, response.data.documentDeepLink);
                }
            });
        },

        onPreview(params) {
            this.isLoadingPreview = true;

            this.documentService.getDocumentPreview(
                this.order.id,
                this.order.deepLinkCode,
                this.currentDocumentType.technicalName,
                params,
            ).then((response) => {
                if (response.data) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(response.data);
                    link.target = '_blank';
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                }

                this.isLoadingPreview = false;
            });
        },

        onOpenDocument(id, deepLink) {
            this.openDocument(id, deepLink);
        },

        onDownload(id, deepLink) {
            this.downloadDocument(id, deepLink);
        },

        onSendDocument(id) {
            this.sendDocument = this.documents.get(id);
            this.showSendDocumentModal = true;
        },

        onMarkDocumentAsSent(id) {
            this.markDocumentAsSent(id);
        },

        onMarkDocumentAsUnsent(id) {
            this.markDocumentAsUnsent(id);
        },

        onCloseSendDocumentModal() {
            this.sendDocument = null;
            this.showSendDocumentModal = false;
        },

        onDocumentSent() {
            this.markDocumentAsSent(this.sendDocument.id);
            this.onCloseSendDocumentModal();
        },

        onLoadingDocument() {
            this.isLoadingDocument = true;
        },

        onLoadingPreview() {
            this.isLoadingPreview = true;
        },

        onShowSelectDocumentTypeModal() {
            this.showSelectDocumentTypeModal = true;
        },

        onCloseSelectDocumentTypeModal(persist) {
            this.showSelectDocumentTypeModal = false;

            if (persist) {
                this.onPrepareDocument();
            }
        },
    },
});
