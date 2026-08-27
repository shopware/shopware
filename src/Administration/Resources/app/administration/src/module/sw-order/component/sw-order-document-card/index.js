/**
 * @sw-package after-sales
 */
import { DocumentEvents } from 'src/core/service/api/document.api.service';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import fileReaderUtils from 'src/core/service/utils/file-reader.utils';
import template from './sw-order-document-card.html.twig';
import './sw-order-document-card.scss';
import EntityCollection from '../../../../core/data/entity-collection.data';
import { DOCUMENT_TYPES, FILE_FORMATS } from '../../service/documentV2.service';

const { Mixin, Store } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
export const ZUGFERD_COMPONENT_MAPPING = {
    [DOCUMENT_TYPES.ZUGFERD_INVOICE]: DOCUMENT_TYPES.INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE]: DOCUMENT_TYPES.INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE]: DOCUMENT_TYPES.CANCELLATION_INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE]: DOCUMENT_TYPES.CANCELLATION_INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE]: DOCUMENT_TYPES.CREDIT_NOTE,
    [DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE]: DOCUMENT_TYPES.CREDIT_NOTE,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'documentService',
        'documentV2ApiService',
        'documentV2Service',
        'numberRangeService',
        'repositoryFactory',
        'acl',
    ],

    emits: [
        'update-loading',
        'document-save',
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
            required: false,
            default: false,
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
            documents: new EntityCollection(null, null, null, new Criteria(1, 25), [], 0),
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
            showUploadDocumentModal: false,
            showSendDocumentModal: false,
            sendDocument: null,
            documentDeleteId: null,
        };
    },

    computed: {
        isEditing: () => Store.get('swOrderDetail').isEditing,

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

            if (this.$.appContext.components[`sw-order-document-settings-${subComponentName}-modal`]) {
                return `sw-order-document-settings-${subComponentName}-modal`;
            }

            const zugferdSubComponentName = ZUGFERD_COMPONENT_MAPPING[this.currentDocumentType.technicalName]?.replace(
                /_/g,
                '-',
            );

            if (this.$.appContext.components[`sw-order-document-settings-${zugferdSubComponentName}-modal`]) {
                return `sw-order-document-settings-${zugferdSubComponentName}-modal`;
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
            criteria
                .addAssociation('documentType')
                .addAssociation('documentMediaFile')
                .addAssociation('documentA11yMediaFile')
                .addAssociation('documentFiles.media');

            criteria.addFilter(Criteria.equals('order.id', this.order.id));

            if (!this.term) {
                return criteria;
            }

            criteria.setTerm(this.term);
            criteria.addQuery(Criteria.contains('config.documentDate', this.term), searchRankingPoint.HIGH_SEARCH_RANKING);
            criteria.addQuery(Criteria.equals('config.documentNumber', this.term), searchRankingPoint.HIGH_SEARCH_RANKING);

            return criteria;
        },

        getDocumentColumns() {
            const columns = [
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-order.documentCard.labelDate',
                    allowResize: false,
                    primary: true,
                },
                {
                    property: 'config.documentNumber',
                    dataIndex: 'config.documentNumber',
                    label: 'sw-order.documentCard.labelNumber',
                    allowResize: false,
                },
                {
                    property: 'documentType.name',
                    dataIndex: 'documentType.name',
                    label: 'sw-order.documentCard.labelType',
                    allowResize: false,
                },
                {
                    property: 'sent',
                    dataIndex: 'sent',
                    label: 'sw-order.documentCard.labelSent',
                    allowResize: false,
                    align: 'center',
                },
            ];

            if (this.$route.name === 'sw.order.detail.documents') {
                columns.splice(3, 0, {
                    property: 'fileTypes',
                    dataIndex: 'fileTypes',
                    label: 'sw-order.documentCard.labelAvailableFormats',
                    allowResize: false,
                    sortable: false,
                });
            }

            if (this.attachView) {
                columns.push({
                    property: 'attach',
                    dataIndex: 'attach',
                    label: 'sw-order.documentCard.labelAttach',
                    allowResize: false,
                    align: 'center',
                });
            }

            if (!this.attachView && this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                columns.push({
                    property: 'documentActions',
                    dataIndex: 'documentActions',
                    label: '',
                    allowResize: false,
                    sortable: false,
                    align: 'right',
                });
            }

            return columns;
        },

        isDataLoading() {
            return this.isLoading || this.documentsLoading || this.cardLoading;
        },

        showCardFilter() {
            return this.order?.documents?.length > 0;
        },

        showCreateDocumentButton() {
            return !this.order?.documents?.length;
        },

        emptyStateTitle() {
            return this.order?.documents?.length > 0
                ? this.$t('sw-order.documentCard.messageNoDocumentFound')
                : this.$t('sw-order.documentCard.messageEmptyTitle');
        },

        tooltipCreateDocumentButton() {
            if (!this.acl.can('document.viewer')) {
                return this.$t('sw-privileges.tooltip.warning');
            }

            return this.$t('sw-order.documentTab.tooltipSaveBeforeCreateDocument');
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        isXmlDocument() {
            return [
                DOCUMENT_TYPES.ZUGFERD_INVOICE,
                DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
                DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            ].includes(this.currentDocumentType?.technicalName);
        },
    },

    watch: {
        isDataLoading: {
            handler(value) {
                this.$emit('update-loading', value);
            },
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
                    errorMessage = this.$t(translationKey, 1, payload.meta.parameters || {});
                }

                this.createNotificationError({
                    message: errorMessage,
                });
            } else if (action === DocumentEvents.DOCUMENT_FINISHED) {
                this.finishDocumentCreation();
            }
        },

        finishDocumentCreation() {
            this.showModal = false;
            this.showSelectDocumentTypeModal = false;
            this.showUploadDocumentModal = false;
            this.currentDocumentType = null;

            return this.$nextTick().then(() => {
                return this.getList().then(() => {
                    this.$emit('document-save');
                });
            });
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

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        documentTypeAvailable(documentType) {
            return (
                (documentType.technicalName !== DOCUMENT_TYPES.CANCELLATION_INVOICE &&
                    documentType.technicalName !== DOCUMENT_TYPES.CREDIT_NOTE) ||
                ((documentType.technicalName === DOCUMENT_TYPES.CANCELLATION_INVOICE ||
                    (documentType.technicalName === DOCUMENT_TYPES.CREDIT_NOTE && this.creditItems.length !== 0)) &&
                    this.invoiceExists())
            );
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        invoiceExists() {
            return this.documents.some((document) => {
                return (
                    document.documentType.technicalName === DOCUMENT_TYPES.INVOICE ||
                    document.documentType.technicalName === DOCUMENT_TYPES.ZUGFERD_INVOICE ||
                    document.documentType.technicalName === DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE
                );
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

        getDocumentFileFormats(document) {
            const v2Formats = (document.documentFiles ?? []).map((documentFile) => documentFile.documentFormat);
            const legacyFormats = [
                document.documentMediaFile?.fileExtension,
                document.documentA11yMediaFile?.fileExtension,
            ].filter((fileType) => fileType);

            return [
                ...new Set([
                    ...v2Formats,
                    ...legacyFormats,
                ]),
            ];
        },

        getDocumentActionFormats(document) {
            const formats = this.getDocumentFileFormats(document);

            if (formats.length === 0) {
                return [FILE_FORMATS.PDF];
            }

            return this.documentV2Service.sortFileFormats(formats);
        },

        hasMultipleDocumentActionFormats(document) {
            return this.getDocumentActionFormats(document).length > 1;
        },

        resolveOpenFileType(document) {
            return this.documentV2Service.getPreferredFileFormat(this.getDocumentFileFormats(document), FILE_FORMATS.PDF);
        },

        resolveDownloadFileType(document) {
            return this.documentV2Service.getPreferredFileFormat(this.getDocumentFileFormats(document), FILE_FORMATS.PDF);
        },

        onCancelCreation() {
            this.showModal = false;
            this.currentDocumentType = null;
        },

        onPrepareDocument() {
            this.showModal = true;
        },

        openDocument(documentId, documentDeepLink, fileType) {
            this.documentService
                .getDocument(documentId, documentDeepLink, Shopware.Context.api, true, fileType)
                .then((response) => {
                    if (response.data) {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(response.data);
                        link.target = '_blank';
                        link.dispatchEvent(new MouseEvent('click'));
                        link.remove();
                    }
                });
        },

        downloadDocument(documentId, documentDeepLink, fileType) {
            if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                this.documentV2ApiService
                    .getDocument(documentId, fileType)
                    .then((documentFileResponse) => {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(documentFileResponse.file);
                        link.download = documentFileResponse.fileName;
                        link.dispatchEvent(new MouseEvent('click'));
                        link.remove();
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$t('sw-order.documentCard.error.downloadDocument'),
                        });
                    });

                return;
            }

            this.documentService
                .getDocument(documentId, documentDeepLink, Shopware.Context.api, true, fileType)
                .then((response) => {
                    if (response.data) {
                        const filename = fileReaderUtils.getFilenameFromResponse(response);
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(response.data);
                        link.download = filename;
                        link.dispatchEvent(new MouseEvent('click'));
                        link.remove();
                    }
                });
        },

        downloadDocumentArchive(documentId) {
            return this.documentV2ApiService
                .getDocumentArchive([documentId])
                .then((documentFileResponse) => {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(documentFileResponse.file);
                    link.download = documentFileResponse.fileName;
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-order.documentCard.error.downloadDocumentArchive'),
                    });
                });
        },

        async sendDocumentAction(documentId) {
            try {
                const documentData = await this.documentRepository.get(
                    documentId,
                    Shopware.Context.api,
                    this.documentCriteria,
                );
                if (!documentData) {
                    return;
                }

                this.sendDocument = documentData;
                this.showSendDocumentModal = true;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.documentCard.error.loadSendingDocument'),
                });
            }
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

        async onCreateDocument(params, additionalAction, referencedDocumentId = null, file = null) {
            this.isLoadingDocument = true;

            if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                let documentCreateResponse;

                try {
                    documentCreateResponse = await this.documentV2ApiService.createDocument(
                        this.order.id,
                        this.currentDocumentType.technicalName,
                        params.requestedFileFormats ?? [],
                        params.documentNumber,
                        params.documentDate,
                        params.documentComment,
                        params.deliveryDate,
                        referencedDocumentId,
                    );
                } catch (err) {
                    this.createNotificationError({
                        message:
                            this.documentV2Service.getErrorTranslation(
                                err.response?.data?.errors?.[0]?.code ?? '',
                                err.response?.data?.errors?.[0]?.meta.parameters ?? [],
                            ) ?? this.$t('sw-order.documentCard.error.createDocument'),
                    });

                    this.isLoadingDocument = false;
                    return;
                }

                const documentId = documentCreateResponse.documentId;

                if (additionalAction === 'download') {
                    const formats = documentCreateResponse.formats ?? params.requestedFileFormats ?? [];

                    if (formats.length > 1) {
                        await this.downloadDocumentArchive(documentId);
                    } else {
                        this.downloadDocument(
                            documentId,
                            null,
                            this.documentV2Service.getPreferredFileFormat(formats, FILE_FORMATS.PDF),
                        );
                    }
                } else if (additionalAction === 'send') {
                    await this.sendDocumentAction(documentId);
                }

                await this.finishDocumentCreation();

                this.isLoadingDocument = false;
                return;
            }

            await this.$nextTick();

            try {
                const response = await this.createDocument(
                    this.order.id,
                    this.currentDocumentType.technicalName,
                    params,
                    referencedDocumentId,
                    file,
                );

                if (!response) {
                    return;
                }

                const documentId = Array.isArray(response) ? response[0].documentId : response?.data?.documentId;

                const documentDeepLink = Array.isArray(response)
                    ? response[0].documentDeepLink
                    : response?.data?.documentDeepLink;

                if (params.documentMediaFileId) {
                    const documentData = await this.documentRepository.get(documentId, Shopware.Context.api);
                    documentData.documentMediaFileId = params.documentMediaFileId;
                    await this.documentRepository.save(documentData);
                }

                if (additionalAction === 'download') {
                    const fileType = this.isXmlDocument ? 'xml' : 'pdf';
                    this.downloadDocument(documentId, documentDeepLink, fileType);
                } else if (additionalAction === 'send') {
                    const criteria = new Criteria(null, null);
                    criteria.addAssociation('documentType').addAssociation('documentA11yMediaFile');

                    this.documentRepository.get(documentId, Shopware.Context.api, criteria).then((documentData) => {
                        if (!documentData) {
                            return;
                        }

                        this.sendDocument = documentData;
                        this.showSendDocumentModal = true;
                    });
                }
            } finally {
                this.isLoadingDocument = false;
            }
        },

        async onUploadDocument(params, additionalAction, file = null) {
            this.isLoadingDocument = true;

            let fileUploadResponse;

            try {
                fileUploadResponse = await this.documentV2ApiService.uploadDocument(
                    this.order.id,
                    this.order.versionId,
                    this.currentDocumentType.technicalName,
                    params.requestedFileFormats?.[0] ?? FILE_FORMATS.PDF,
                    params.documentNumber,
                    params.documentMediaFileId,
                    file,
                );
            } catch (err) {
                this.createNotificationError({
                    message:
                        this.documentV2Service.getErrorTranslation(
                            err.response?.data?.errors?.[0]?.code ?? '',
                            err.response?.data?.errors?.[0]?.meta.parameters ?? [],
                        ) ?? this.$t('sw-order.documentCard.error.uploadDocument'),
                });

                this.isLoadingDocument = false;
                return;
            }

            const documentId = fileUploadResponse.documentId;

            if (params.documentMediaFileId) {
                try {
                    const documentData = await this.documentRepository.get(documentId, Shopware.Context.api);
                    documentData.documentMediaFileId = params.documentMediaFileId;
                    await this.documentRepository.save(documentData);
                } catch {
                    this.createNotificationError({
                        message: 'sw-order.documentCard.error.attachMediaToDocumentUpload',
                    });

                    this.isLoadingDocument = false;
                    return;
                }
            }

            if (additionalAction === 'send') {
                await this.sendDocumentAction(documentId);
            }

            await this.finishDocumentCreation();

            this.isLoadingDocument = false;
        },

        onPreview(params, fileType) {
            this.isLoadingPreview = true;

            if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                return this.documentV2ApiService
                    .previewDocument(
                        this.order.id,
                        this.currentDocumentType.technicalName,
                        fileType,
                        params.documentNumber,
                        params.documentDate,
                        params.documentComment,
                    )
                    .then((documentFileResponse) => {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(documentFileResponse.file);
                        link.target = '_blank';
                        link.dispatchEvent(new MouseEvent('click'));
                        link.remove();
                    })
                    .catch(async (err) => {
                        const errorData = await err.response.data.text();
                        let message;

                        try {
                            const errorJson = JSON.parse(errorData);
                            message =
                                this.documentV2Service.getErrorTranslation(
                                    errorJson.errors?.[0]?.code ?? '',
                                    errorJson.errors?.[0]?.meta.parameters ?? [],
                                ) ?? this.$t('sw-order.documentCard.error.loadDocumentPreview');
                        } catch {
                            message = this.$t('sw-order.documentCard.error.loadDocumentPreview');
                        }

                        this.createNotificationError({
                            message: message,
                        });
                    })
                    .finally(() => {
                        this.isLoadingPreview = false;
                    });
            }

            return this.documentService
                .getDocumentPreview(this.order.id, this.order.deepLinkCode, this.currentDocumentType.technicalName, params, {
                    fileType,
                })
                .then((response) => {
                    if (response.data) {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(response.data);
                        link.target = '_blank';
                        link.dispatchEvent(new MouseEvent('click'));
                        link.remove();
                    }

                    return response;
                })
                .finally(() => {
                    this.isLoadingPreview = false;
                });
        },

        onOpenDocument(id, deepLink, fileType) {
            if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                this.documentV2ApiService
                    .getDocument(id, fileType)
                    .then((documentFileResponse) => {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(documentFileResponse.file);
                        link.target = '_blank';
                        link.dispatchEvent(new MouseEvent('click'));
                        link.remove();
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$t('sw-order.documentCard.error.openDocument'),
                        });
                    });

                return;
            }

            this.openDocument(id, deepLink, fileType);
        },

        onDownload(id, deepLink, fileType) {
            this.downloadDocument(id, deepLink, fileType);
        },

        onDownloadAll(document) {
            this.downloadDocumentArchive(document.id);
        },

        onShowDeleteDocumentModal(id) {
            this.documentDeleteId = id;
        },

        onCloseDeleteDocumentModal() {
            this.documentDeleteId = null;
        },

        onDeleteDocument(id) {
            this.documentDeleteId = null;

            return this.documentRepository
                .delete(id, this.documents.context ?? Shopware.Context.api)
                .then(() => {
                    return this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-order.documentCard.notificationDeleteErrorMessage'),
                    });
                });
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

        onCloseCreateDocumentModal() {
            this.showSelectDocumentTypeModal = false;
            this.currentDocumentType = null;
        },

        onShowUploadDocumentModal() {
            this.showSelectDocumentTypeModal = false;
            this.showUploadDocumentModal = true;
        },

        onCloseUploadDocumentModal() {
            this.showUploadDocumentModal = false;
            this.currentDocumentType = null;
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
            this.showUploadDocumentModal = false;
            this.showSelectDocumentTypeModal = true;
        },

        onCloseSelectDocumentTypeModal(persist) {
            this.showSelectDocumentTypeModal = false;

            if (persist) {
                this.onPrepareDocument();
            }
        },

        availableFormatsFilter(item) {
            return this.getDocumentFileFormats(item)
                .map((format) => this.$t(this.documentV2Service.getFileFormatSnippet(format)))
                .join(', ');
        },
    },
};
