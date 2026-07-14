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
import { getDocumentNumberRangeType, isDocumentTypeAvailable } from '../document-type-selection.utils';

const { Component, Mixin, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { isEmpty } = Utils.types;

const FILE_FORMAT_PRIORITY = [
    'pdf',
    'html',
    'zugferd_xml',
    'zugferd_embedded_pdf',
];

const FILE_FORMAT_MIME_TYPES: Record<string, string> = {
    html: 'text/html',
    pdf: 'application/pdf',
    zugferd_embedded_pdf: 'application/pdf',
    zugferd_xml: 'application/xml,text/xml',
};

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

function createEmptyDocumentConfig() {
    const now = new Date().toISOString();

    return {
        documentComment: '',
        documentDate: now,
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
            documentConfig: createEmptyDocumentConfig(),
            documentNumberPreview: '',
            documentTypeCollection: null,
            documentTypeId: this.value?.id ?? null,
            documentTypeLoading: false,
            documentTypes: [],
            features: {
                uploadFileSizeLimit: 52428800,
            },
            invoiceExists: false,
            isLoading: false,
            selectedDocumentFile: null,
            selectedFileFormat: null,
            showMediaModal: false,
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

        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            return new Criteria(1, 100).addSorting(Criteria.sort('name', 'ASC'));
        },

        documentCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('order.id', this.order.id));
            criteria.addFilter(
                Criteria.equalsAny('documentType.technicalName', [
                    DOCUMENT_TYPES.INVOICE,
                    DOCUMENT_TYPES.ZUGFERD_INVOICE,
                    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
                ]),
            );

            return criteria;
        },

        documentTypeOptions() {
            return this.documentTypes.map((documentType) => {
                return {
                    disabled: !this.documentTypeAvailable(documentType),
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

        typeSpecificColumns() {
            return '1fr 1fr';
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
        async createdComponent() {
            this.isLoading = true;

            try {
                const [
                    documentCollection,
                    response,
                    supportResponse,
                ] = await Promise.all([
                    this.documentRepository.searchIds(this.documentCriteria),
                    this.documentTypeRepository.search(this.documentTypeCriteria),
                    this.documentV2Service.getAvailableTypes(),
                ]);

                this.supportedDocumentTypes = supportResponse.data?.documentTypes ?? {};
                this.invoiceExists = documentCollection.total > 0;
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

        documentTypeAvailable(documentType) {
            return isDocumentTypeAvailable(documentType.technicalName, this.invoiceExists, this.creditItems.length);
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

        translateFileFormat(format) {
            const translationKey = {
                html: 'sw-order.components.createDocumentModal.fileFormats.html',
                pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                zugferd_embedded_pdf: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                zugferd_xml: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
            }[format];

            return translationKey ? this.$t(translationKey) : format;
        },

        async onDocumentTypeChange(documentType) {
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
