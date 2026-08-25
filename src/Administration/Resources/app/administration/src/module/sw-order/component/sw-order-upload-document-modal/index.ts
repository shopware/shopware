import type RepositoryType from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import type { DocumentConfig } from '../../service/documentV2.service';
import { DOCUMENT_TYPES, FILE_FORMAT_MIME_TYPES } from '../../service/documentV2.service';
import type { AvailableDocumentTypesResponse } from '../../../../core/service/api/documentV2.api.service';
import template from './sw-order-upload-document-modal.html.twig';
import './sw-order-upload-document-modal.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const FILE_SIZE_LIMIT = 52428800; // 50 MB

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
        'document-upload',
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
        documentTypeLoading: boolean;
        documentTypeCollection: EntityCollection<'document_type'> | null;
        documentTypeId: string | null;
        documentTypes: Entity<'document_type'>[];
        features: { uploadFileSizeLimit: number };
        isLoading: boolean;
        supportedDocumentTypes: NonNullable<AvailableDocumentTypesResponse['documentTypes']>;
        selectedDocumentFile: Entity<'media'> | null;
        selectedFileFormat: string | null;
    } {
        return {
            documentConfig: this.documentV2Service.createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeLoading: false,
            documentTypeCollection: null,
            documentTypeId: this.documentType?.id ?? null,
            documentTypes: [],
            features: {
                uploadFileSizeLimit: FILE_SIZE_LIMIT,
            },
            isLoading: false,
            supportedDocumentTypes: {},
            selectedDocumentFile: null,
            selectedFileFormat: null,
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
                    label: this.$t(this.documentV2Service.getDocumentTypeSnippet(documentType.technicalName)),
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
                !this.currentDocumentType ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                !this.selectedFileFormat ||
                (!this.selectedDocumentFile && !this.documentConfig.documentMediaFileId)
            );
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

            try {
                this.documentTypeCollection = await this.documentTypeRepository.search(this.documentTypeCriteria);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.components.createDocumentModal.error.loadDocumentTypes'),
                });

                this.isLoading = false;

                return;
            }

            try {
                this.supportedDocumentTypes = await this.documentV2Service.getAvailableDocumentTypes();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.components.createDocumentModal.error.loadSupportedDocumentFileFormats'),
                });

                this.isLoading = false;

                return;
            }

            this.documentTypes = this.documentTypeCollection.filter(
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

            this.isLoading = false;
        },

        async onDocumentTypeChange(documentType: Entity<'document_type'> | null): Promise<void> {
            this.selectedFileFormat = null;
            this.removeCustomDocument();

            if (!documentType) {
                this.documentConfig = this.documentV2Service.createEmptyDocumentConfig();
                this.documentNumberPreview = '';

                return;
            }

            this.documentTypeLoading = true;

            this.documentConfig = this.documentV2Service.createEmptyDocumentConfig(documentType.technicalName);

            try {
                const documentNumber = await this.reserveDocumentNumber(documentType.technicalName, true);

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

        async onUploadDocument(additionalAction = ''): Promise<void> {
            if (this.invalidInput || !this.currentDocumentType) {
                return;
            }

            if (this.documentNumberPreview === this.documentConfig.documentNumber) {
                let documentNumber;

                try {
                    documentNumber = await this.reserveDocumentNumber(this.currentDocumentType.technicalName, false);
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

            this.$emit(
                'document-upload',
                {
                    ...this.documentConfig,
                    requestedFileFormats: [this.selectedFileFormat],
                },
                additionalAction,
                this.selectedDocumentFile,
            );
        },

        onCancel(): void {
            this.$emit('page-leave');
        },

        successfulUploadFromUrl(res: { targetId: EntityKey<'media'> }): void {
            this.mediaRepository
                .get(res.targetId)
                .then((response) => {
                    if (response) {
                        this.validateFile(response);
                    }
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-order.components.createDocumentModal.error.loadUploadedDocument'),
                    });
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
