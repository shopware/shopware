/**
 * @sw-package after-sales
 */
import { DocumentEvents } from 'src/core/service/api/document.api.service';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import fileReaderUtils from 'src/core/service/utils/file-reader.utils';
import template from './sw-order-document-card.html.twig';
import './sw-order-document-card.scss';
import EntityCollection from '../../../../core/data/entity-collection.data';
import { DOCUMENT_TYPES } from '../../order.types';

const { Mixin, Store } = Shopware;
const { Criteria } = Shopware.Data;

const FILE_TYPE_PRIORITY = [
    'pdf',
    'html',
    'zugferd_embedded_pdf',
    'zugferd_xml',
];

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

            if (!this.attachView) {
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
            this.documentV2Service.setListener(this.convertStoreEventToVueEvent);
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
            if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                if (file || params.documentMediaFileId) {
                    return this.documentV2Service.uploadDocument(
                        orderId,
                        this.order.versionId,
                        documentTypeName,
                        params.requestedFormats?.[0] ?? 'pdf',
                        params.documentNumber,
                        params.documentDate,
                        params.documentComment,
                        params.documentMediaFileId,
                        file,
                        referencedDocumentId,
                    );
                }

                return this.documentV2Service.createDocument(
                    orderId,
                    this.order.versionId,
                    documentTypeName,
                    params.requestedFormats ?? [],
                    params.documentNumber,
                    params.documentDate,
                    params.documentComment,
                );
            }

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

        getDocumentFileTypes(document) {
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

        getPreferredFileType(fileTypes = []) {
            return (
                [...fileTypes].sort((left, right) => {
                    return this.getFileTypePriority(left) - this.getFileTypePriority(right);
                })[0] ?? 'pdf'
            );
        },

        getDocumentActionFormats(document) {
            const formats = this.getDocumentFileTypes(document);

            if (formats.length === 0) {
                return ['pdf'];
            }

            return [...formats].sort((left, right) => {
                return this.getFileTypePriority(left) - this.getFileTypePriority(right);
            });
        },

        getFileTypePriority(fileType) {
            const priority = FILE_TYPE_PRIORITY.indexOf(fileType);

            return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
        },

        hasMultipleDocumentActionFormats(document) {
            return this.getDocumentActionFormats(document).length > 1;
        },

        getDocumentFormatLabel(format) {
            return (
                {
                    pdf: 'PDF',
                    html: 'HTML',
                    zugferd_embedded_pdf: 'ZUGFeRD PDF',
                    zugferd_xml: 'ZUGFeRD XML',
                }[format] ?? format.toUpperCase()
            );
        },

        resolveOpenFileType(document) {
            return this.getPreferredFileType(this.getDocumentFileTypes(document));
        },

        resolveDownloadFileType(document) {
            return this.getPreferredFileType(this.getDocumentFileTypes(document));
        },

        onCancelCreation() {
            this.showModal = false;
            this.currentDocumentType = null;
        },

        onPrepareDocument() {
            this.showModal = true;
        },

        openDocument(documentId, deepLinkCode, fileType) {
            const openRequest = this.feature.isActive('DOCUMENT_GENERATION_REWORK')
                ? this.documentV2Service.getDocument(documentId, fileType)
                : this.documentService.getDocument(documentId, deepLinkCode, Shopware.Context.api, true, fileType);

            openRequest.then((response) => {
                if (response.data) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(response.data);
                    link.target = '_blank';
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                }
            });
        },

        downloadDocument(documentId, deepLinkCode, fileType) {
            const downloadRequest = this.feature.isActive('DOCUMENT_GENERATION_REWORK')
                ? this.documentV2Service.getDocument(documentId, fileType)
                : this.documentService.getDocument(documentId, deepLinkCode, Shopware.Context.api, true, fileType);

            return downloadRequest.then((response) => {
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
            return this.documentV2Service.getDocumentArchive(documentId).then((response) => {
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

                const deepLinkCode = Array.isArray(response)
                    ? (response[0].deepLinkCode ?? response[0].documentDeepLink)
                    : (response?.data?.deepLinkCode ?? response?.data?.documentDeepLink);

                if (params.documentMediaFileId) {
                    const documentData = await this.documentRepository.get(documentId, Shopware.Context.api);
                    documentData.documentMediaFileId = params.documentMediaFileId;
                    await this.documentRepository.save(documentData);
                }

                if (additionalAction === 'download') {
                    if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                        const formats = response?.data?.formats ?? params.requestedFormats ?? [];

                        if (formats.length > 1) {
                            await this.downloadDocumentArchive(documentId);
                            await this.finishDocumentCreation();

                            return;
                        }

                        await this.downloadDocument(documentId, deepLinkCode, this.getPreferredFileType(formats));
                        await this.finishDocumentCreation();

                        return;
                    }

                    const format = this.isXmlDocument ? 'xml' : 'pdf';

                    this.downloadDocument(documentId, deepLinkCode, format);
                } else if (additionalAction === 'send') {
                    const criteria = new Criteria(null, null);
                    criteria
                        .addAssociation('documentType')
                        .addAssociation('documentA11yMediaFile')
                        .addAssociation('documentFiles.media');

                    await this.documentRepository.get(documentId, Shopware.Context.api, criteria).then((documentData) => {
                        if (!documentData) {
                            return;
                        }

                        this.sendDocument = documentData;
                        this.showSendDocumentModal = true;
                    });
                }

                if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                    await this.finishDocumentCreation();
                }
            } finally {
                this.isLoadingDocument = false;
            }
        },

        onPreview(params, format) {
            this.isLoadingPreview = true;

            const previewRequest = this.feature.isActive('DOCUMENT_GENERATION_REWORK')
                ? this.documentV2Service.previewDocument(
                      this.order.id,
                      this.order.versionId,
                      this.currentDocumentType.technicalName,
                      format,
                      params.documentNumber,
                      params.documentDate,
                      params.documentComment,
                  )
                : this.documentService.getDocumentPreview(
                      this.order.id,
                      this.order.deepLinkCode,
                      this.currentDocumentType.technicalName,
                      params,
                      { fileType: format },
                  );

            return previewRequest
                .then((response) => {
                    if (response?.data) {
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
            this.openDocument(id, deepLink, fileType);
        },

        onDownload(id, deepLink, fileType) {
            this.downloadDocument(id, deepLink, fileType);
        },

        onDownloadAll(document) {
            if (this.feature.isActive('DOCUMENT_GENERATION_REWORK')) {
                this.downloadDocumentArchive(document.id);

                return;
            }

            this.getDocumentActionFormats(document).forEach((format) => {
                this.onDownload(document.id, document.deepLinkCode, format);
            });
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
                .catch(() => {});
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
            return this.getDocumentFileTypes(item).join(', ').toUpperCase();
        },
    },
};
