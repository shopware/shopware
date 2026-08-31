import type { AvailableDocumentTypesResponse } from '../../../../core/service/api/documentV2.api.service';
import { DOCUMENT_TYPES, INVOICE_DOCUMENT_TYPES, FILE_FORMATS } from '../../service/documentV2.service';
import type { DocumentConfig } from '../../service/documentV2.service';
import template from './sw-order-create-document-modal.html.twig';
import './sw-order-create-document-modal.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @sw-package after-sales
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'documentV2Service',
        'numberRangeService',
    ],

    emits: [
        'document-create',
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
            type: Object as PropType<{ technicalName: string }>,
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
        selectedTechnicalName: string | null;
        referencedDocumentNumber: string | null;
        isLoading: boolean;
        supportedDocumentTypes: NonNullable<AvailableDocumentTypesResponse['documentTypes']>;
    } {
        return {
            documentConfig: this.documentV2Service.createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeLoading: false,
            selectedTechnicalName: this.documentType?.technicalName ?? null,
            referencedDocumentNumber: null,
            isLoading: false,
            supportedDocumentTypes: {},
        };
    },

    computed: {
        creditItems(): Entity<'order_line_item'>[] {
            return (this.order.lineItems ?? []).filter((lineItem) => lineItem.type === 'credit');
        },

        currentTechnicalName(): string | null {
            if (this.selectedTechnicalName === null) {
                return null;
            }

            if (!(this.selectedTechnicalName in this.supportedDocumentTypes)) {
                return null;
            }

            return this.selectedTechnicalName;
        },

        documentNumberErrorMessage(): { detail: string } | null {
            if (!this.currentTechnicalName || this.documentConfig.documentNumber) {
                return null;
            }

            return {
                detail: this.$t('global.notification.notificationSaveErrorMessageRequiredField'),
            };
        },

        referencedDocumentNumberErrorMessage(): { detail: string } | null {
            if (!this.isCreditNoteDocument && !this.isStornoDocument) {
                return null;
            }

            if (!this.referencedDocumentNumber) {
                return {
                    detail: this.$t('global.notification.notificationSaveErrorMessageRequiredField'),
                };
            }

            if (this.isCreditNoteDocument && this.creditItems.length === 0) {
                return {
                    detail: this.$t('sw-order.documentModal.errorInvoiceMissingCreditItem'),
                };
            }

            return null;
        },

        documentTypeOptions(): { label: string; value: string }[] {
            return Object.keys(this.supportedDocumentTypes).map((technicalName) => {
                return {
                    label: this.documentV2Service.getDocumentTypeLabel(
                        technicalName,
                        this.supportedDocumentTypes[technicalName]?.label,
                    ),
                    value: technicalName,
                };
            });
        },

        documentFamily(): string | null {
            return this.documentV2Service.getDocumentFamily(this.currentTechnicalName);
        },

        fileFormatOptions(): { label: string; value: string }[] {
            if (!this.currentTechnicalName) {
                return [];
            }

            const formats = this.supportedDocumentTypes[this.currentTechnicalName]?.formats ?? [];

            return this.documentV2Service.sortFileFormats(formats).map((format) => {
                return {
                    label: this.$t(this.documentV2Service.getFileFormatSnippet(format)),
                    value: format,
                };
            });
        },

        isModalLoading(): boolean {
            return this.isLoading || this.documentTypeLoading;
        },

        invalidInput(): boolean {
            return (
                !this.currentTechnicalName ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                this.documentConfig.requestedFileFormats.length === 0 ||
                (this.isReferencingOtherDocument && !this.referencedDocumentNumber) ||
                (this.isCreditNoteDocument && this.creditItems.length === 0)
            );
        },

        referencedDocumentNumberOptions(): { label: string; value: string }[] {
            if (!this.order.documents) {
                return [];
            }

            const referencedDocumentNumbers =
                this.isCreditNoteDocument || this.isStornoDocument
                    ? this.documentV2Service.getDocumentNumbersByTypes(this.order.documents, INVOICE_DOCUMENT_TYPES)
                    : [];

            return [...new Set(referencedDocumentNumbers)].sort().map((documentNumber) => {
                return {
                    label: String(documentNumber),
                    value: documentNumber,
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

        isReferencingOtherDocument(): boolean {
            return this.isCreditNoteDocument || this.isStornoDocument;
        },

        previewFileFormatOptions(): { label: string; value: string }[] {
            return this.documentConfig.requestedFileFormats
                .map((format) => {
                    return this.fileFormatOptions.find((option) => option.value === format) ?? null;
                })
                .filter((option): option is { label: string; value: string } => option !== null);
        },
    },

    watch: {
        selectedTechnicalName: {
            async handler(value: string | null): Promise<void> {
                this.$emit('update:documentType', value ? { technicalName: value } : null);

                await this.onDocumentTypeChange(value);
            },
        },
    },

    created(): void {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                this.supportedDocumentTypes = await this.documentV2Service.getAvailableDocumentTypes();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.components.createDocumentModal.error.loadDocumentTypes'),
                });

                this.isLoading = false;

                return;
            }

            if (this.selectedTechnicalName && !(this.selectedTechnicalName in this.supportedDocumentTypes)) {
                this.selectedTechnicalName = null;
            }

            if (this.selectedTechnicalName) {
                await this.onDocumentTypeChange(this.selectedTechnicalName);
            }

            this.isLoading = false;
        },

        async onDocumentTypeChange(technicalName: string | null): Promise<void> {
            if (!technicalName) {
                this.documentConfig = this.documentV2Service.createEmptyDocumentConfig();
                this.documentNumberPreview = '';
                this.referencedDocumentNumber = null;

                return;
            }

            this.documentTypeLoading = true;

            this.documentConfig = this.documentV2Service.createEmptyDocumentConfig(technicalName);

            this.referencedDocumentNumber = null;

            try {
                const documentNumber = await this.reserveDocumentNumber(technicalName, true);

                this.documentConfig.documentNumber = documentNumber;
                this.documentNumberPreview = documentNumber;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.components.createDocumentModal.error.loadDocumentNumber'),
                });
            } finally {
                this.documentTypeLoading = false;
            }
        },

        async reserveDocumentNumber(technicalName: string, isPreview = false): Promise<string> {
            const numberRangeService = this.numberRangeService as {
                reserve: (type: string, salesChannelId: string, preview: boolean) => Promise<{ number: string }>;
            };

            const { number } = await numberRangeService.reserve(
                `document_${this.documentV2Service.getDocumentNumberRangeType(technicalName)}`,
                this.order.salesChannelId,
                isPreview,
            );

            return number;
        },

        async onCreateDocument(additionalAction = ''): Promise<void> {
            if (this.invalidInput || !this.currentTechnicalName) {
                return;
            }

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                let documentNumber;

                try {
                    documentNumber = await this.reserveDocumentNumber(this.currentTechnicalName, false);
                } catch {
                    this.createNotificationError({
                        message: this.$t('sw-order.components.createDocumentModal.error.loadDocumentNumber'),
                    });

                    return;
                }

                if (documentNumber !== this.documentConfig.documentNumber) {
                    this.createNotificationInfo({
                        message: this.$t('sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'),
                    });
                }

                this.documentConfig.documentNumber = documentNumber;
            }

            this.$emit('document-create', this.documentConfig, additionalAction, this.getReferencedDocumentId());
        },

        onPreview(format: string | null = null): void {
            if (!this.currentTechnicalName) {
                return;
            }

            this.$emit(
                'preview-show',
                this.documentConfig,
                format ||
                    this.documentV2Service.getPreferredFileFormat(
                        this.documentConfig.requestedFileFormats,
                        FILE_FORMATS.PDF,
                    ),
            );
        },

        onCancel(): void {
            this.$emit('page-leave');
        },

        getReferencedDocumentId(): string | null {
            if (!this.order.documents || !this.referencedDocumentNumber) {
                return null;
            }

            return (
                this.order.documents.find((document) => document.documentNumber === this.referencedDocumentNumber)?.id ??
                null
            );
        },
    },
});
