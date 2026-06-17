/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-save-modal-success.html.twig';
import './sw-bulk-edit-save-modal-success.scss';
import fileReaderUtils from '../../../../core/service/utils/file-reader.utils';
import { DOCUMENT_TYPES } from '../../../sw-order/order.types';

const { Criteria } = Shopware.Data;
const documentTypeOrder = [
    DOCUMENT_TYPES.INVOICE,
    DOCUMENT_TYPES.CANCELLATION_INVOICE,
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.DELIVERY_NOTE,
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'orderDocumentApiService',
    ],

    emits: [
        'title-set',
        'buttons-update',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            latestDocuments: {},
            orderNumbers: {},
            document: {
                invoice: {
                    isDownloading: false,
                },
                storno: {
                    isDownloading: false,
                },
                delivery_note: {
                    isDownloading: false,
                },
                credit_note: {
                    isDownloading: false,
                },
            },
        };
    },

    computed: {
        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        selectedIds() {
            return Shopware.Store.get('swBulkEdit').selectedIds;
        },

        downloadOrderDocuments() {
            return Shopware.Store.get('swBulkEdit')?.orderDocuments?.download;
        },

        latestDocumentsCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(
                Criteria.equalsAny(
                    'documentTypeId',
                    this.selectedDocumentTypes.map((item) => item.id),
                ),
            );
            criteria.addFilter(Criteria.equalsAny('orderId', this.selectedIds));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        selectedDocumentTypes() {
            if (!this.downloadOrderDocuments) {
                return [];
            }

            if (!this.downloadOrderDocuments.isChanged) {
                return [];
            }

            if (this.downloadOrderDocuments.value.length <= 0) {
                return [];
            }

            return this.downloadOrderDocuments.value.filter((item) => item.selected);
        },

        description() {
            return this.selectedDocumentTypes.length > 0
                ? this.$t('sw-bulk-edit.modal.success.instruction')
                : this.$t('sw-bulk-edit.modal.success.description');
        },

        documentGenerationResult() {
            return Shopware.Store.get('swBulkEdit').documentGenerationResult;
        },

        documentGenerationFailedItems() {
            return this.documentGenerationResult.failedItems ?? [];
        },

        hasSkippedDocuments() {
            return this.documentGenerationResult.skipped > 0;
        },

        hasDocumentGenerationErrors() {
            return this.documentGenerationResult.failed > 0;
        },

        hasFailedDocumentRows() {
            return this.failedDocumentRows.length > 0;
        },

        documentTypeLabels() {
            const documentTypes = Array.isArray(this.downloadOrderDocuments?.value) ? this.downloadOrderDocuments.value : [];
            const labels = {
                [DOCUMENT_TYPES.INVOICE]: this.$t('sw-bulk-edit.modal.success.failedDocuments.documentTypes.invoice'),
                [DOCUMENT_TYPES.CANCELLATION_INVOICE]: this.$t(
                    'sw-bulk-edit.modal.success.failedDocuments.documentTypes.storno',
                ),
                [DOCUMENT_TYPES.CREDIT_NOTE]: this.$t('sw-bulk-edit.modal.success.failedDocuments.documentTypes.creditNote'),
                [DOCUMENT_TYPES.DELIVERY_NOTE]: this.$t(
                    'sw-bulk-edit.modal.success.failedDocuments.documentTypes.deliveryNote',
                ),
            };

            documentTypes.forEach((documentType) => {
                labels[documentType.technicalName] = documentType.translated?.name ?? documentType.name;
            });

            return labels;
        },

        failedDocumentRows() {
            const rows = [];
            const rowsByOrderId = {};

            this.documentGenerationFailedItems.forEach((failedItem) => {
                if (!rowsByOrderId[failedItem.orderId]) {
                    rowsByOrderId[failedItem.orderId] = {
                        id: failedItem.orderId,
                        orderId: failedItem.orderId,
                        orderNumber: this.orderNumbers[failedItem.orderId] ?? failedItem.orderId,
                        documentTypes: [],
                    };

                    rows.push(rowsByOrderId[failedItem.orderId]);
                }

                if (!rowsByOrderId[failedItem.orderId].documentTypes.includes(failedItem.documentType)) {
                    rowsByOrderId[failedItem.orderId].documentTypes.push(failedItem.documentType);
                }
            });

            return rows.map((row) => {
                const documentTypes = this.sortDocumentTypes(row.documentTypes);

                return {
                    ...row,
                    documentTypes,
                    documentTypesLabel: documentTypes
                        .map((documentType) => this.getDocumentTypeLabel(documentType))
                        .join(', '),
                };
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.updateButtons();
            this.setTitle();
            await Promise.all([
                this.getLatestDocuments(),
                this.loadFailedOrderNumbers(),
            ]);
            this.updateButtons();
        },

        setTitle() {
            this.$emit('title-set', this.$t('sw-bulk-edit.modal.success.title'));
        },

        updateButtons() {
            const buttonConfig = [];

            if (this.hasFailedDocumentRows) {
                buttonConfig.push({
                    key: 'download-result',
                    label: this.$t('sw-bulk-edit.modal.success.failedDocuments.downloadResult'),
                    position: 'right',
                    variant: 'secondary',
                    action: () => this.downloadDocumentGenerationResult(),
                    disabled: false,
                });
            }

            buttonConfig.push({
                key: 'close',
                label: this.$t('global.default.close'),
                position: 'right',
                variant: 'primary',
                action: '',
                disabled: false,
            });

            this.$emit('buttons-update', buttonConfig);
        },

        async loadFailedOrderNumbers() {
            if (this.documentGenerationFailedItems.length <= 0) {
                return;
            }

            const orderIds = [...new Set(this.documentGenerationFailedItems.map((failedItem) => failedItem.orderId))];
            const criteria = new Criteria(1, orderIds.length);
            criteria.addFilter(Criteria.equalsAny('id', orderIds));

            try {
                const orders = await this.orderRepository.search(criteria);
                const orderNumbers = {};

                orders.forEach((order) => {
                    orderNumbers[order.id] = order.orderNumber;
                });

                this.orderNumbers = orderNumbers;
            } catch {
                this.orderNumbers = {};
            }
        },

        async getLatestDocuments() {
            if (this.selectedDocumentTypes.length <= 0) {
                return;
            }

            const latestDocuments = {};
            const maxDocsPerType = this.selectedIds.length;

            const documents = await this.documentRepository.search(this.latestDocumentsCriteria);

            this.selectedDocumentTypes.forEach((documentType) => {
                latestDocuments[documentType.technicalName] ??= [];
                const latestDoc = latestDocuments[documentType.technicalName];

                const documentsGrouped = documents.filter((document) => {
                    return document.documentTypeId === documentType.id;
                });

                const latestDocKeyedByOrderId = {};

                documentsGrouped.forEach((doc) => {
                    if (Object.values(latestDoc).length === maxDocsPerType) {
                        return;
                    }

                    if (!latestDocKeyedByOrderId.hasOwnProperty(doc.orderId)) {
                        latestDocKeyedByOrderId[doc.orderId] = doc.id;
                        latestDoc.push(doc.id);
                    }
                });
            });

            this.latestDocuments = latestDocuments;
        },

        downloadDocument(documentType) {
            const documentIds = this.latestDocuments[documentType];

            if (!documentIds || documentIds.length === 0) {
                this.createNotificationInfo({
                    message: this.$t('sw-bulk-edit.modal.success.messageNoDocumentsFound'),
                });

                return Promise.resolve();
            }

            if (!this.document[documentType]) {
                this.document[documentType] = {};
            }

            this.document[documentType].isDownloading = true;
            return this.orderDocumentApiService
                .download(documentIds)
                .then((response) => {
                    if (!response.data) {
                        return;
                    }

                    const filename = fileReaderUtils.getFilenameFromResponse(response);
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(response.data);
                    link.download = filename;
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });
                })
                .finally(() => {
                    this.document[documentType].isDownloading = false;
                });
        },

        sortDocumentTypes(documentTypes) {
            return [...documentTypes].sort((firstDocumentType, secondDocumentType) => {
                const firstIndex = documentTypeOrder.indexOf(firstDocumentType);
                const secondIndex = documentTypeOrder.indexOf(secondDocumentType);

                return (
                    (firstIndex === -1 ? documentTypeOrder.length : firstIndex) -
                    (secondIndex === -1 ? documentTypeOrder.length : secondIndex)
                );
            });
        },

        getDocumentTypeLabel(documentType) {
            return this.documentTypeLabels[documentType] ?? documentType;
        },

        downloadDocumentGenerationResult() {
            const objectUrl = URL.createObjectURL(
                new Blob(
                    [
                        this.getDocumentGenerationResultFileContent(),
                    ],
                    {
                        type: 'text/plain',
                    },
                ),
            );
            const link = document.createElement('a');

            link.href = objectUrl;
            link.download = this.getDocumentGenerationResultFileName();
            link.dispatchEvent(new MouseEvent('click'));
            link.remove();

            URL.revokeObjectURL(objectUrl);
        },

        getDocumentGenerationResultFileContent() {
            return [
                this.$t('sw-bulk-edit.modal.success.failedDocuments.downloadHeadline'),
                '',
                ...this.failedDocumentRows.map((row) => {
                    return `${row.orderNumber} - ${row.documentTypesLabel}`;
                }),
            ].join('\n');
        },

        getDocumentGenerationResultFileName() {
            return (
                [
                    this.$t('sw-bulk-edit.modal.success.failedDocuments.downloadFileName'),
                    this.getDateTimeForFileName(new Date()),
                ].join('-') + '.txt'
            );
        },

        getDateTimeForFileName(date) {
            const pad = (value) => value.toString().padStart(2, '0');

            return [
                date.getFullYear(),
                pad(date.getMonth() + 1),
                pad(date.getDate()),
                pad(date.getHours()),
                pad(date.getMinutes()),
            ].join('-');
        },
    },
};
