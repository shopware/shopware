import type RepositoryType from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import type { AvailableDocumentTypesResponse } from 'src/core/service/api/documentV2.api.service';
import { DOCUMENT_TYPES, INVOICE_DOCUMENT_TYPES } from 'src/core/service/documentV2.service';
import type { DocumentConfig } from 'src/core/service/documentV2.service';
import template from './sw-order-create-document-modal.html.twig';
import './sw-order-create-document-modal.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;


/**
 * @private
 * @sw-package after-sales
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'documentV2ApiService',
        'documentV2Service',
        'numberRangeService',
        'repositoryFactory',
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
        referencedDocumentNumber: string | null,
        isLoading: boolean;
        supportedDocumentTypes: NonNullable<AvailableDocumentTypesResponse['documentTypes']>;
    } {
        return {
            documentConfig: this.documentV2Service.createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeLoading: false,
            documentTypeCollection: null,
            documentTypeId: this.documentType?.id ?? null,
            documentTypes: [],
            referencedDocumentNumber: null,
            isLoading: false,
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

        referencedDocumentNumberErrorMessage(): { detail: string } | null {
            if ((!this.isCreditNoteDocument && !this.isStornoDocument) || this.referencedDocumentNumber) {
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
            return this.documentV2Service.getDocumentFamily(this.currentDocumentType?.technicalName ?? null);
        },

        fileFormatOptions(): { label: string; value: string }[] {
            if (!this.currentDocumentType?.technicalName) {
                return [];
            }

            const formats = this.supportedDocumentTypes[this.currentDocumentType.technicalName]?.formats ?? [];

            return this.documentV2Service.sortFileFormats(formats)
                .map((format) => {
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
                !this.currentDocumentType ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                this.documentConfig.requestedFileFormats.length === 0 ||
                (this.isCreditNoteDocument && this.creditItems.length === 0)
            );
        },

        referencedDocumentNumberOptions(): { label: string; value: string }[] {
            if (!this.order.documents) {
                return [];
            }

            const referencedDocumentNumbers = this.isCreditNoteDocument || this.isStornoDocument ? this.documentV2Service.getDocumentNumbersByTypes(this.order.documents, INVOICE_DOCUMENT_TYPES) : [];

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

            try {
                const [
                    response,
                    supportResponse,
                ] = await Promise.all([
                    this.documentTypeRepository.search(this.documentTypeCriteria),
                    this.documentV2ApiService.getAvailableTypes(),
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

        async onDocumentTypeChange(documentType: Entity<'document_type'> | null): Promise<void> {
            if (!documentType) {
                this.documentConfig = this.documentV2Service.createEmptyDocumentConfig();
                this.documentNumberPreview = '';

                return;
            }

            this.documentTypeLoading = true;

            try {
                this.documentConfig = this.documentV2Service.createEmptyDocumentConfig(documentType.technicalName);

                const documentNumber = await this.reserveDocumentNumber(documentType.technicalName, true);

                this.documentConfig.documentNumber = documentNumber;
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
                `document_${this.documentV2Service.getDocumentNumberRangeType(technicalName)}`,
                this.order.salesChannelId,
                isPreview,
            );

            return number;
        },

        async onCreateDocument(additionalAction = false): Promise<void> {
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

            this.$emit(
                'document-create',
                this.documentConfig,
                additionalAction,
                this.getReferencedDocumentId(),
            );
        },

        onPreview(format: string | null = null): void {
            if (!this.currentDocumentType) {
                return;
            }

            this.$emit(
                'preview-show',
                this.documentConfig,
                format || this.documentV2Service.getPreferredFileFormat(this.documentConfig.requestedFileFormats, 'pdf'),
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
                this.order.documents.find(
                    (document) =>
                        document.documentNumber === this.referencedDocumentNumber,
                )?.id ?? null
            );
        },
    },
});
