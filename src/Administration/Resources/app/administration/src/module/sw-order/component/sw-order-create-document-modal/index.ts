import type RepositoryType from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-order-create-document-modal.html.twig';
import './sw-order-create-document-modal.scss';
import { DOCUMENT_TYPES } from '../../order.types';
import {
    FILE_FORMAT_PRIORITY,
    getDocumentFamily,
    getDocumentNumberRangeType,
    INVOICE_DOCUMENT_TYPES,
} from '../document-type-selection.utils';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

interface DocumentConfigCustom {
    invoiceNumber?: string;
    deliveryDate?: string;
    deliveryNoteNumber?: string;
    creditNoteNumber?: string;
    stornoNumber?: string;
}

interface DocumentConfig {
    custom: DocumentConfigCustom;
    documentComment: string;
    documentDate: string;
    documentNumber: string;
}

interface DocumentEntityConfig {
    custom?: {
        invoiceNumber?: string;
    };
}

interface DocumentTypeFormats {
    formats: string[];
}

function createEmptyDocumentConfig(technicalName: string | null = null): DocumentConfig {
    const now = new Date().toISOString();
    const documentFamily = getDocumentFamily(technicalName);

    const custom: DocumentConfigCustom = {};

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
        'update:documentType',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        order: {
            type: Object as PropType<Entity<'order'>>,
            required: true,
        },

        documentType: {
            type: Object as PropType<Entity<'document_type'>>,
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

    data(): {
        documentConfig: DocumentConfig;
        documentNumberPreview: string;
        documentTypeLoading: boolean;
        documentTypeCollection: EntityCollection<'document_type'> | null;
        documentTypeId: string | null;
        documentTypes: Entity<'document_type'>[];
        isLoading: boolean;
        selectedFileFormats: string[];
        supportedDocumentTypes: Record<string, DocumentTypeFormats>;
    } {
        return {
            documentConfig: createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeLoading: false,
            documentTypeCollection: null,
            documentTypeId: this.documentType?.id ?? null,
            documentTypes: [],
            isLoading: false,
            selectedFileFormats: [],
            supportedDocumentTypes: {},
        };
    },

    computed: {
        creditItems(): Entity<'order_line_item'>[] {
            return (this.order.lineItems ?? []).filter((lineItem) => lineItem.type === 'credit');
        },

        currentDocumentType(): Entity<'document_type'> | null {
            if (!this.documentTypeId) {
                return null;
            }

            return this.documentTypeCollection?.get(this.documentTypeId) ?? null;
        },

        documentTypeRepository(): RepositoryType<'document_type'> {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria(): CriteriaType {
            return new Criteria(1, 100).addSorting(Criteria.sort('name', 'ASC'));
        },

        documentNumberErrorMessage(): { detail: string } | null {
            if (!this.currentDocumentType || this.documentConfig.documentNumber) {
                return null;
            }

            return {
                detail: this.$t('global.notification.notificationSaveErrorMessageRequiredField'),
            };
        },

        documentTypeOptions(): { label: string; value: string }[] {
            return this.documentTypes.map((documentType) => {
                return {
                    label: documentType.translated?.name ?? '',
                    value: documentType.id,
                };
            });
        },

        documentFamily(): string | null {
            return getDocumentFamily(this.currentDocumentType?.technicalName);
        },

        documentPreconditionsFulfilled(): boolean {
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

        fileFormatOptions(): { label: string; value: string }[] {
            if (!this.currentDocumentType?.technicalName) {
                return [];
            }

            return this.getFileFormatOptions(this.currentDocumentType.technicalName);
        },

        isModalLoading(): boolean {
            return this.isLoading || this.documentTypeLoading;
        },

        invalidInput(): boolean {
            return (
                !this.currentDocumentType ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                this.selectedFileFormats.length === 0 ||
                !this.documentPreconditionsFulfilled
            );
        },

        invoiceDocuments(): Entity<'document'>[] {
            return this.getInvoiceDocuments(this.order.documents ?? []);
        },

        invoiceNumberOptions(): { label: string; value: string }[] {
            const invoiceNumbers = this.invoiceDocuments
                .map((item) => (item.config as DocumentEntityConfig | undefined)?.custom?.invoiceNumber)
                .filter((invoiceNumber): invoiceNumber is string => !!invoiceNumber);

            return [...new Set(invoiceNumbers)].sort().map((invoiceNumber) => {
                return {
                    label: String(invoiceNumber),
                    value: invoiceNumber,
                };
            });
        },

        isCreditNoteDocument(): boolean {
            return this.documentFamily === DOCUMENT_TYPES.CREDIT_NOTE;
        },

        isDeliveryNoteDocument(): boolean {
            return this.documentFamily === DOCUMENT_TYPES.DELIVERY_NOTE;
        },

        isStornoDocument(): boolean {
            return this.documentFamily === DOCUMENT_TYPES.CANCELLATION_INVOICE;
        },

        previewFileFormatOptions(): { label: string; value: string }[] {
            return this.selectedFileFormats
                .map((format) => {
                    return this.fileFormatOptions.find((option) => option.value === format) ?? null;
                })
                .filter((option): option is { label: string; value: string } => option !== null);
        },
    },

    watch: {
        documentTypeId: {
            async handler(value: string | null): Promise<void> {
                if (!this.documentTypeCollection) {
                    return;
                }

                const documentType = value ? this.documentTypeCollection.get(value) : null;

                this.$emit('update:documentType', documentType);

                await this.onDocumentTypeChange(documentType);
            },
        },
    },

    created(): void {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            const documentV2Service = this.documentV2Service as {
                getAvailableTypes: () => Promise<{
                    data?: { documentTypes?: Record<string, DocumentTypeFormats> };
                }>;
            };

            try {
                const [
                    response,
                    supportResponse,
                ] = await Promise.all([
                    this.documentTypeRepository.search(this.documentTypeCriteria),
                    documentV2Service.getAvailableTypes(),
                ]);

                this.supportedDocumentTypes = supportResponse.data?.documentTypes ?? {};
                this.documentTypeCollection = response;
                this.documentTypes = response.filter(
                    (documentType) => documentType.technicalName in this.supportedDocumentTypes,
                );

                const documentTypeId = this.documentTypeId;

                if (documentTypeId) {
                    const documentType = this.documentTypeCollection.get(documentTypeId);

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

        async onDocumentTypeChange(documentType: Entity<'document_type'> | null): Promise<void> {
            this.selectedFileFormats = [];

            if (!documentType) {
                this.documentConfig = createEmptyDocumentConfig();
                this.documentNumberPreview = '';

                return;
            }

            this.documentTypeLoading = true;

            try {
                const nextDocumentConfig = createEmptyDocumentConfig(documentType.technicalName);
                const documentNumber = await this.reserveDocumentNumber(documentType.technicalName, true);

                nextDocumentConfig.documentNumber = documentNumber;
                this.documentConfig = nextDocumentConfig;
                this.documentNumberPreview = documentNumber;
            } finally {
                this.documentTypeLoading = false;
            }
        },

        async reserveDocumentNumber(technicalName: string, isPreview = false): Promise<string> {
            const numberRangeService = this.numberRangeService as {
                reserve: (type: string, salesChannelId: string, preview: boolean) => Promise<{ number: string }>;
            };

            const { number } = await numberRangeService.reserve(
                `document_${getDocumentNumberRangeType(technicalName)}`,
                this.order.salesChannelId,
                isPreview,
            );

            return number;
        },

        async onCreateDocument(additionalAction = false): Promise<void> {
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

        onPreview(format: string | null = null): void {
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

        onCancel(): void {
            this.$emit('page-leave');
        },

        applyTypeSpecificConfiguration(): void {
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

        getReferencedDocumentId(): string | null {
            if (!this.isCreditNoteDocument && !this.isStornoDocument) {
                return null;
            }

            return (
                this.invoiceDocuments.find(
                    (item) =>
                        (item.config as DocumentEntityConfig | undefined)?.custom?.invoiceNumber ===
                        this.documentConfig.custom.invoiceNumber,
                )?.id ?? null
            );
        },

        getFileFormatOptions(technicalName: string): { label: string; value: string }[] {
            const formats = this.supportedDocumentTypes[technicalName]?.formats ?? [];

            return [...formats]
                .sort((left, right) => this.getFileFormatPriority(left) - this.getFileFormatPriority(right))
                .map((format) => {
                    return {
                        label: this.translateFileFormat(format),
                        value: format,
                    };
                });
        },

        getPreferredFileFormat(formats?: string[]): string | null {
            const targetFormats = formats ?? this.selectedFileFormats;

            return (
                [...targetFormats].sort(
                    (left, right) => this.getFileFormatPriority(left) - this.getFileFormatPriority(right),
                )[0] ?? null
            );
        },

        getFileFormatPriority(fileFormat: string): number {
            const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

            return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
        },

        getInvoiceDocuments(documents: Entity<'document'>[]): Entity<'document'>[] {
            return documents.filter((document) => {
                const technicalName = document.documentType?.technicalName;

                return typeof technicalName === 'string' && INVOICE_DOCUMENT_TYPES.includes(technicalName);
            });
        },

        translateFileFormat(format: string): string {
            const translationKey = (
                {
                    html: 'sw-order.components.createDocumentModal.fileFormats.html',
                    pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                    zugferd_embedded_pdf: 'sw-order.components.createDocumentModal.fileFormats.zugferdEmbeddedPdf',
                    zugferd_xml: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
                } as Record<string, string>
            )[format];

            return translationKey ? this.$t(translationKey) : format;
        },
    },
});
