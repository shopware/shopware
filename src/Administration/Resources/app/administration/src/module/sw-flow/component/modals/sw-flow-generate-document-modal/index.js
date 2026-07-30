import template from './sw-flow-generate-document-modal.html.twig';
import { translateDocumentFileFormat } from '../../../constant/document-file-format.constant';

const { Component, Mixin, Store } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'documentV2Service',
    ],

    emits: [
        'modal-close',
        'process-finish',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            documentTypesSelected: [],
            documentTypeSelected: null,
            fileFormatsSelected: [],
            supportedDocumentTypes: {},
            isLoadingSupportedDocumentTypes: false,
            fieldError: null,
            fileFormatsFieldError: null,
        };
    },

    computed: {
        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        isDocumentGenerationReworkActive() {
            return Shopware.Feature.isActive('DOCUMENT_GENERATION_REWORK');
        },

        documentTypeOptions() {
            return this.documentTypes.filter((documentType) => documentType.technicalName in this.supportedDocumentTypes);
        },

        fileFormatOptions() {
            const formats = this.supportedDocumentTypes[this.documentTypeSelected]?.formats ?? [];

            return formats.map((format) => {
                return {
                    value: format,
                    label: translateDocumentFileFormat(format, this.$t),
                };
            });
        },

        ...mapState(() => Store.get('swFlow'), ['documentTypes']),
    },

    watch: {
        documentTypesSelected(value) {
            if (value.length > 0 && this.fieldError) {
                this.fieldError = null;
            }
        },

        fileFormatsSelected(value) {
            if (value.length > 0 && this.fileFormatsFieldError) {
                this.fileFormatsFieldError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            if (!this.documentTypes.length) {
                this.documentTypeRepository.search(this.documentTypeCriteria).then((data) => {
                    Shopware.Store.get('swFlow').documentTypes = data;
                });
            }

            if (!this.isDocumentGenerationReworkActive) {
                this.initializeLegacyState();

                return;
            }

            await this.loadSupportedDocumentTypes();

            this.documentTypeSelected =
                this.sequence?.config?.documentType ?? this.sequence?.config?.documentTypes?.[0]?.documentType ?? null;

            this.fileFormatsSelected = this.sequence?.config?.fileFormats || [];
        },

        initializeLegacyState() {
            if (this.sequence?.config?.documentType) {
                this.documentTypesSelected = [this.sequence.config];
            } else {
                this.documentTypesSelected = this.sequence?.config?.documentTypes || [];
            }

            this.documentTypesSelected = this.documentTypesSelected.map((type) => {
                return type.documentType;
            });
        },

        async loadSupportedDocumentTypes() {
            this.isLoadingSupportedDocumentTypes = true;

            try {
                const response = await this.documentV2Service.getAvailableTypes();
                this.supportedDocumentTypes = response.data?.documentTypes ?? {};
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoadingSupportedDocumentTypes = false;
            }
        },

        onDocumentTypeSelectedChange(value) {
            this.documentTypeSelected = value;
            this.fileFormatsSelected = [];

            if (this.fieldError) {
                this.fieldError = null;
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            if (!this.isDocumentGenerationReworkActive) {
                this.onAddLegacyAction();

                return;
            }

            let hasError = false;

            if (!this.documentTypeSelected) {
                this.fieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                hasError = true;
            }

            if (!this.fileFormatsSelected.length) {
                this.fileFormatsFieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                hasError = true;
            }

            if (hasError) {
                return;
            }

            const sequence = {
                ...this.sequence,
                config: {
                    documentType: this.documentTypeSelected,
                    fileFormats: [...this.fileFormatsSelected],
                },
            };

            this.$emit('process-finish', sequence);
        },

        onAddLegacyAction() {
            if (!this.documentTypesSelected.length) {
                this.fieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            const documentTypes = this.documentTypesSelected.map((documentType) => {
                return {
                    documentType: documentType,
                    documentRangerType: `document_${documentType}`,
                };
            });

            const sequence = {
                ...this.sequence,
                config: {
                    documentTypes,
                },
            };

            this.$emit('process-finish', sequence);
        },
    },
};
