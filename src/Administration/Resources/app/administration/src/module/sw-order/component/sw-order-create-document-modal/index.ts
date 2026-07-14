/* eslint-disable
    @typescript-eslint/no-unsafe-argument,
    @typescript-eslint/no-unsafe-assignment,
    @typescript-eslint/no-unsafe-call,
    @typescript-eslint/no-unsafe-member-access,
    @typescript-eslint/no-unsafe-return
*/
import template from './sw-order-create-document-modal.html.twig';
import './sw-order-create-document-modal.scss';

import { DOCUMENT_TYPES } from '../../order.types';
import { getDocumentNumberRangeType, getInvoiceDocuments } from '../document-type-selection.utils';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const FILE_FORMAT_PRIORITY = [
    'pdf',
    'html',
    'zugferd_xml',
    'zugferd_embedded_pdf',
];

function getFileFormatPriority(fileFormat: string): number {
    const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

    return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
}

function getDocumentFamily(technicalName?: string | null): string | null {
    if (!technicalName) {
        return null;
    }

    if (
        [
            DOCUMENT_TYPES.INVOICE,
            DOCUMENT_TYPES.ZUGFERD_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
        ].includes(technicalName as (typeof DOCUMENT_TYPES)[keyof typeof DOCUMENT_TYPES])
    ) {
        return DOCUMENT_TYPES.INVOICE;
    }

    if (
        [
            DOCUMENT_TYPES.CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
        ].includes(technicalName as (typeof DOCUMENT_TYPES)[keyof typeof DOCUMENT_TYPES])
    ) {
        return DOCUMENT_TYPES.CREDIT_NOTE;
    }

    if (
        [
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
        ].includes(technicalName as (typeof DOCUMENT_TYPES)[keyof typeof DOCUMENT_TYPES])
    ) {
        return DOCUMENT_TYPES.CANCELLATION_INVOICE;
    }

    return technicalName;
}

function createEmptyDocumentConfig(technicalName: string | null = null) {
    const now = new Date().toISOString();
    const documentFamily = getDocumentFamily(technicalName);

    const custom: Record<string, string> = {};

    if (documentFamily === DOCUMENT_TYPES.CREDIT_NOTE || documentFamily === DOCUMENT_TYPES.CANCELLATION_INVOICE) {
        custom.invoiceNumber = '';
    }

    if (documentFamily === DOCUMENT_TYPES.DELIVERY_NOTE) {
        custom.deliveryDate = now;
    }

    return {
        custom,
        documentComment: '',
        documentDate: now,
        documentNumber: '',
    };
}

/**
 * @private
 * @sw-package after-sales
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'documentV2Service',
        'numberRangeService',
        'repositoryFactory',
    ],

    emits: [
        'document-create',
        'loading-document',
        'loading-preview',
        'page-leave',
        'preview-show',
        'update:value',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        order: {
            type: Object,
            required: true,
        },

        value: {
            type: Object,
            required: false,
            default: null,
        },

        isLoadingDocument: {
            type: Boolean,
            required: true,
        },

        isLoadingPreview: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            documentConfig: createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeLoading: false,
            documentTypeCollection: null,
            documentTypeId: this.value?.id ?? null,
            documentTypes: [],
            isLoading: false,
            selectedFileFormats: [],
            supportedDocumentTypes: {},
        };
    },

    computed: {
        creditItems() {
            return this.order.lineItems.filter((lineItem) => lineItem.type === 'credit');
        },

        currentDocumentType() {
            return this.documentTypeCollection?.get(this.documentTypeId) ?? null;
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            return new Criteria(1, 100).addSorting(Criteria.sort('name', 'ASC'));
        },

        documentNumberErrorMessage() {
            if (!this.currentDocumentType || this.documentConfig.documentNumber) {
                return null;
            }

            return {
                detail: this.$t('global.notification.notificationSaveErrorMessageRequiredField'),
            };
        },

        documentTypeOptions() {
            return this.documentTypes.map((documentType) => {
                return {
                    label: documentType.translated.name,
                    value: documentType.id,
                };
            });
        },

        documentFamily() {
            return getDocumentFamily(this.currentDocumentType?.technicalName);
        },

        documentPreconditionsFulfilled() {
            switch (this.documentFamily) {
                case DOCUMENT_TYPES.CREDIT_NOTE:
                    return this.creditItems.length !== 0 && !!this.documentConfig.custom.invoiceNumber;
                case DOCUMENT_TYPES.CANCELLATION_INVOICE:
                    return !!this.documentConfig.custom.invoiceNumber;
                case DOCUMENT_TYPES.DELIVERY_NOTE:
                    return !!this.documentConfig.custom.deliveryDate;
                default:
                    return true;
            }
        },

        fileFormatOptions() {
            if (!this.currentDocumentType?.technicalName) {
                return [];
            }

            return this.getFileFormatOptions(this.currentDocumentType.technicalName);
        },

        isModalLoading() {
            return this.isLoading || this.documentTypeLoading;
        },

        invalidInput() {
            return (
                !this.currentDocumentType ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                this.selectedFileFormats.length === 0 ||
                !this.documentPreconditionsFulfilled
            );
        },

        invoiceDocuments() {
            return getInvoiceDocuments(this.order.documents || []);
        },

        invoiceNumberOptions() {
            const invoiceNumbers = this.invoiceDocuments
                .map((item) => item.config?.custom?.invoiceNumber)
                .filter((invoiceNumber) => !!invoiceNumber);

            return [...new Set(invoiceNumbers)].sort().map((invoiceNumber) => {
                return {
                    label: String(invoiceNumber),
                    value: invoiceNumber,
                };
            });
        },

        isCreditNoteDocument() {
            return this.documentFamily === DOCUMENT_TYPES.CREDIT_NOTE;
        },

        isDeliveryNoteDocument() {
            return this.documentFamily === DOCUMENT_TYPES.DELIVERY_NOTE;
        },

        isStornoDocument() {
            return this.documentFamily === DOCUMENT_TYPES.CANCELLATION_INVOICE;
        },

        previewFileFormatOptions() {
            return this.selectedFileFormats
                .map((format) => {
                    return this.fileFormatOptions.find((option) => option.value === format) ?? null;
                })
                .filter((option) => option !== null);
        },

        typeSpecificColumns() {
            return this.isCreditNoteDocument || this.isDeliveryNoteDocument || this.isStornoDocument
                ? '1fr 1fr 1fr'
                : '1fr 1fr';
        },
    },

    watch: {
        documentTypeId: {
            async handler(value) {
                if (!this.documentTypeCollection) {
                    return;
                }

                const documentType = value ? this.documentTypeCollection.get(value) : null;

                this.$emit('update:value', documentType);

                await this.onDocumentTypeChange(documentType);
            },
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        createEmptyDocumentConfig(technicalName = null) {
            return createEmptyDocumentConfig(technicalName);
        },

        async createdComponent() {
            this.isLoading = true;

            try {
                const [
                    response,
                    supportResponse,
                ] = await Promise.all([
                    this.documentTypeRepository.search(this.documentTypeCriteria),
                    this.documentV2Service.getAvailableTypes(),
                ]);

                this.supportedDocumentTypes = supportResponse.data?.documentTypes ?? {};
                this.documentTypeCollection = response;
                this.documentTypes = response.filter(
                    (documentType) => documentType.technicalName in this.supportedDocumentTypes,
                );

                if (this.documentTypeId) {
                    const documentType = this.documentTypeCollection.get(this.documentTypeId);

                    if (!documentType || !(documentType.technicalName in this.supportedDocumentTypes)) {
                        this.documentTypeId = null;
                        return;
                    }

                    await this.onDocumentTypeChange(documentType);
                }
            } finally {
                this.isLoading = false;
            }
        },

        async onDocumentTypeChange(documentType) {
            this.selectedFileFormats = [];

            if (!documentType) {
                this.documentConfig = this.createEmptyDocumentConfig();
                this.documentNumberPreview = '';

                return;
            }

            this.documentTypeLoading = true;

            try {
                const nextDocumentConfig = this.createEmptyDocumentConfig(documentType.technicalName);
                const documentNumber = await this.reserveDocumentNumber(documentType.technicalName, true);

                nextDocumentConfig.documentNumber = documentNumber;
                this.documentConfig = nextDocumentConfig;
                this.documentNumberPreview = documentNumber;
            } finally {
                this.documentTypeLoading = false;
            }
        },

        async reserveDocumentNumber(technicalName, isPreview = false) {
            const { number } = await this.numberRangeService.reserve(
                `document_${getDocumentNumberRangeType(technicalName)}`,
                this.order.salesChannelId,
                isPreview,
            );

            return number;
        },

        async onCreateDocument(additionalAction = false) {
            this.$emit('loading-document');

            if (this.invalidInput || !this.currentDocumentType) {
                return;
            }

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                const documentNumber = await this.reserveDocumentNumber(this.currentDocumentType.technicalName, false);

                if (documentNumber !== this.documentConfig.documentNumber) {
                    this.createNotificationInfo({
                        message: this.$t('sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'),
                    });
                }

                this.documentConfig.documentNumber = documentNumber;
            }

            this.applyTypeSpecificConfiguration();

            this.$emit(
                'document-create',
                {
                    ...this.documentConfig,
                    requestedFormats: [...this.selectedFileFormats],
                },
                additionalAction,
                this.getReferencedDocumentId(),
            );
        },

        onPreview(format = null) {
            if (!this.currentDocumentType) {
                return;
            }

            const previewFormat = format || this.getPreferredFileFormat();

            if (!previewFormat) {
                return;
            }

            this.$emit('loading-preview');

            this.applyTypeSpecificConfiguration();

            this.$emit(
                'preview-show',
                {
                    ...this.documentConfig,
                    formats: [previewFormat],
                    requestedFormats: [...this.selectedFileFormats],
                },
                previewFormat,
            );
        },

        onCancel() {
            this.$emit('page-leave');
        },

        applyTypeSpecificConfiguration() {
            switch (this.documentFamily) {
                case DOCUMENT_TYPES.INVOICE:
                    this.documentConfig.custom.invoiceNumber = this.documentConfig.documentNumber;
                    break;
                case DOCUMENT_TYPES.DELIVERY_NOTE:
                    this.documentConfig.custom.deliveryNoteNumber = this.documentConfig.documentNumber;
                    break;
                case DOCUMENT_TYPES.CREDIT_NOTE:
                    this.documentConfig.custom.creditNoteNumber = this.documentConfig.documentNumber;
                    break;
                case DOCUMENT_TYPES.CANCELLATION_INVOICE:
                    this.documentConfig.custom.stornoNumber = this.documentConfig.documentNumber;
                    break;
                default:
            }
        },

        getReferencedDocumentId() {
            if (!this.isCreditNoteDocument && !this.isStornoDocument) {
                return null;
            }

            return (
                this.invoiceDocuments.find(
                    (item) => item.config?.custom?.invoiceNumber === this.documentConfig.custom.invoiceNumber,
                )?.id ?? null
            );
        },

        getFileFormatOptions(technicalName) {
            const formats = this.supportedDocumentTypes[technicalName]?.formats ?? [];

            return [...formats]
                .sort((left, right) => getFileFormatPriority(left) - getFileFormatPriority(right))
                .map((format) => {
                    return {
                        label: this.translateFileFormat(format),
                        value: format,
                    };
                });
        },

        getPreferredFileFormat(formats = this.selectedFileFormats) {
            return [...formats].sort((left, right) => getFileFormatPriority(left) - getFileFormatPriority(right))[0] ?? null;
        },

        translateFileFormat(format) {
            const translationKey = {
                html: 'sw-order.components.createDocumentModal.fileFormats.html',
                pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                zugferd_embedded_pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                zugferd_xml: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
            }[format];

            return translationKey ? this.$t(translationKey) : format;
        },
    },
});
