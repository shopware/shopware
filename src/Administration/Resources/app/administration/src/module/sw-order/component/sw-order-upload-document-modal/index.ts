/* eslint-disable
    @typescript-eslint/no-unsafe-argument,
    @typescript-eslint/no-unsafe-assignment,
    @typescript-eslint/no-unsafe-call,
    @typescript-eslint/no-unsafe-member-access,
    @typescript-eslint/no-unsafe-return
*/
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
    },

    data() {
        return {
            documentConfig: this.createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeCollection: null,
            documentTypeId: this.value?.id ?? null,
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
        currentDocumentType() {
            return this.documentTypeCollection?.get(this.documentTypeId) ?? null;
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            return new Criteria(1, 100).addSorting(Criteria.sort('name', 'ASC'));
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

        documentNumberErrorMessage() {
            if (!this.currentDocumentType || this.documentConfig.documentNumber) {
                return null;
            }

            return {
                detail: this.$t('global.notification.notificationSaveErrorMessageRequiredField'),
            };
        },

        fileFormatOptions() {
            if (!this.currentDocumentType?.technicalName) {
                return [];
            }

            return this.getFileFormatOptions(this.currentDocumentType.technicalName);
        },

        fileAcceptTypes() {
            if (this.selectedFileFormat) {
                return FILE_FORMAT_MIME_TYPES[this.selectedFileFormat] ?? '*/*';
            }

            const mimeTypes = this.fileFormatOptions.flatMap((option) => {
                return (FILE_FORMAT_MIME_TYPES[option.value] ?? '').split(',');
            });

            return [...new Set(mimeTypes.filter((mimeType) => mimeType !== ''))].join(',');
        },

        invalidInput() {
            return (
                !this.currentDocumentType ||
                !this.documentConfig.documentNumber ||
                !this.documentConfig.documentDate ||
                !this.selectedFileFormat ||
                (!this.selectedDocumentFile && !this.documentConfig.documentMediaFileId)
            );
        },

        isModalLoading() {
            return this.isLoading || this.documentTypeLoading;
        },

        isStornoDocument() {
            return this.documentFamily === DOCUMENT_TYPES.CANCELLATION_INVOICE;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
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

        selectedFileFormat() {
            this.removeCustomDocument();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        createEmptyDocumentConfig() {
            return {
                documentComment: '',
                documentDate: new Date().toISOString(),
                documentMediaFileId: null,
                documentNumber: '',
            };
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

        getFileFormatOptions(technicalName) {
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

        getFileFormatPriority(fileFormat) {
            const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

            return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
        },

        translateFileFormat(format) {
            const translationKey = {
                html: 'sw-order.components.createDocumentModal.fileFormats.html',
                pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                zugferd_embedded_pdf: 'sw-order.components.createDocumentModal.fileFormats.zugferdEmbeddedPdf',
                zugferd_xml: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
            }[format];

            return translationKey ? this.$t(translationKey) : format;
        },

        async onDocumentTypeChange(documentType) {
            this.selectedFileFormat = null;
            this.selectedDocumentFile = null;

            if (!documentType) {
                this.documentConfig = this.createEmptyDocumentConfig();
                this.documentNumberPreview = '';

                return;
            }

            this.documentTypeLoading = true;

            try {
                const nextDocumentConfig = this.createEmptyDocumentConfig();
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

        async onUploadDocument(additionalAction = false) {
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

        onCancel() {
            this.$emit('page-leave');
        },

        openMediaModal() {
            this.showMediaModal = true;
        },

        closeMediaModal() {
            this.showMediaModal = false;
        },

        onAddMediaFromLibrary(media) {
            if (isEmpty(media)) {
                return;
            }

            this.validateFile(media[0]);
        },

        successfulUploadFromUrl(res) {
            void this.mediaRepository.get(res.targetId).then((response) => {
                this.validateFile(response);
            });
        },

        validateFile(response) {
            if (this.$refs.fileInput.checkFileSize(response) && this.$refs.fileInput.checkFileType(response)) {
                this.selectedDocumentFile = response;
                this.documentConfig.documentMediaFileId = response.id;
            }
        },

        removeCustomDocument() {
            this.documentConfig.documentMediaFileId = null;
            this.selectedDocumentFile = null;
        },

        onAddDocument(data) {
            this.selectedDocumentFile = data[0];
            this.documentConfig.documentMediaFileId = null;
        },
    },
});
