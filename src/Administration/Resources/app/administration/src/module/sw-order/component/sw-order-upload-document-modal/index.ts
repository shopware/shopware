import type RepositoryType from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-order-upload-document-modal.html.twig';
import './sw-order-upload-document-modal.scss';
import { DOCUMENT_TYPES } from '../../order.types';
import {
    FILE_FORMAT_MIME_TYPES,
    FILE_FORMAT_PRIORITY,
    getDocumentFamily,
    getDocumentNumberRangeType,
} from '../document-type-selection.utils';

const { Component, Mixin, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { isEmpty } = Utils.types;

interface DocumentConfig {
    documentComment: string;
    documentDate: string;
    documentMediaFileId: string | null;
    documentNumber: string;
}

interface DocumentTypeFormats {
    formats: string[];
}

function createEmptyDocumentConfig(): DocumentConfig {
    return {
        documentComment: '',
        documentDate: new Date().toISOString(),
        documentMediaFileId: null,
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
        'page-leave',
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
    },

    data(): {
        documentConfig: DocumentConfig;
        documentNumberPreview: string;
        documentTypeCollection: EntityCollection<'document_type'> | null;
        documentTypeId: string | null;
        documentTypeLoading: boolean;
        documentTypes: Entity<'document_type'>[];
        features: { uploadFileSizeLimit: number };
        isLoading: boolean;
        selectedDocumentFile: Entity<'media'> | null;
        selectedFileFormat: string | null;
        showMediaModal: boolean;
        supportedDocumentTypes: Record<string, DocumentTypeFormats>;
    } {
        return {
            documentConfig: createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeCollection: null,
            documentTypeId: this.documentType?.id ?? null,
            documentTypeLoading: false,
            documentTypes: [],
            features: {
                uploadFileSizeLimit: 52428800,
            },
            isLoading: false,
            selectedDocumentFile: null,
            selectedFileFormat: null,
            showMediaModal: false,
            supportedDocumentTypes: {},
        };
    },

    computed: {
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

        documentNumberErrorMessage(): { detail: string } | null {
            if (!this.currentDocumentType || this.documentConfig.documentNumber) {
                return null;
            }

            return {
                detail: this.$t('global.notification.notificationSaveErrorMessageRequiredField'),
            };
        },

        fileFormatOptions(): { label: string; value: string }[] {
            if (!this.currentDocumentType?.technicalName) {
                return [];
            }

            return this.getFileFormatOptions(this.currentDocumentType.technicalName);
        },

        fileAcceptTypes(): string {
            if (this.selectedFileFormat) {
                return FILE_FORMAT_MIME_TYPES[this.selectedFileFormat] ?? '*/*';
            }

            const mimeTypes = this.fileFormatOptions.flatMap((option) => {
                return (FILE_FORMAT_MIME_TYPES[option.value] ?? '').split(',');
            });

            return [...new Set(mimeTypes.filter((mimeType) => mimeType !== ''))].join(',');
        },

        invalidInput(): boolean {
            return (
                !this.currentDocumentType ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                !this.selectedFileFormat ||
                (!this.selectedDocumentFile && !this.documentConfig.documentMediaFileId)
            );
        },

        isModalLoading(): boolean {
            return this.isLoading || this.documentTypeLoading;
        },

        isStornoDocument(): boolean {
            return this.documentFamily === DOCUMENT_TYPES.CANCELLATION_INVOICE;
        },

        mediaRepository(): RepositoryType<'media'> {
            return this.repositoryFactory.create('media');
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

        selectedFileFormat(): void {
            this.removeCustomDocument();
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

        getFileFormatPriority(fileFormat: string): number {
            const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

            return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
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

        async onDocumentTypeChange(documentType: Entity<'document_type'> | null): Promise<void> {
            this.selectedFileFormat = null;
            this.selectedDocumentFile = null;

            if (!documentType) {
                this.documentConfig = createEmptyDocumentConfig();
                this.documentNumberPreview = '';

                return;
            }

            this.documentTypeLoading = true;

            try {
                const nextDocumentConfig = createEmptyDocumentConfig();
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

        async onUploadDocument(additionalAction = false): Promise<void> {
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

            this.$emit(
                'document-create',
                {
                    ...this.documentConfig,
                    requestedFormats: [this.selectedFileFormat],
                },
                additionalAction,
                null,
                this.selectedDocumentFile,
            );
        },

        onCancel(): void {
            this.$emit('page-leave');
        },

        openMediaModal(): void {
            this.showMediaModal = true;
        },

        closeMediaModal(): void {
            this.showMediaModal = false;
        },

        onAddMediaFromLibrary(media: Entity<'media'>[]): void {
            if (isEmpty(media)) {
                return;
            }

            this.validateFile(media[0]);
        },

        successfulUploadFromUrl(res: { targetId: string }): void {
            void this.mediaRepository.get(res.targetId).then((response) => {
                if (response) {
                    this.validateFile(response);
                }
            });
        },

        validateFile(response: Entity<'media'>): void {
            const fileInput = this.$refs.fileInput as {
                checkFileSize: (file: Entity<'media'>) => boolean;
                checkFileType: (file: Entity<'media'>) => boolean;
            };

            if (fileInput.checkFileSize(response) && fileInput.checkFileType(response)) {
                this.selectedDocumentFile = response;
                this.documentConfig.documentMediaFileId = response.id;
            }
        },

        removeCustomDocument(): void {
            this.documentConfig.documentMediaFileId = null;
            this.selectedDocumentFile = null;
        },

        onAddDocument(data: Entity<'media'>[]): void {
            this.selectedDocumentFile = data[0];
            this.documentConfig.documentMediaFileId = null;
        },
    },
});
