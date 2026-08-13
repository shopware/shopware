/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-generate-invoice.html.twig';
import './sw-bulk-edit-order-documents-generate-invoice.scss';

const { Store } = Shopware;

// TODO get from service
const FILE_FORMAT_PRIORITY = [
    'pdf',
    'html',
    'zugferd_embedded_pdf',
    'zugferd_xml',
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        feature: {},
        documentV2ApiService: {
            default: null,
        },
    },

    data() {
        return {
            supportedDocumentTypes: {},
        };
    },

    computed: {
        generateData: {
            get() {
                return Store.get('swBulkEdit')?.orderDocuments?.invoice?.value;
            },
            set(generateData) {
                Store.get('swBulkEdit').setOrderDocumentsValue({
                    type: 'invoice',
                    value: generateData,
                });
            },
        },

        documentTypeTechnicalName() {
            return 'invoice';
        },

        fileFormatOptions() {
            const formats = this.supportedDocumentTypes[this.documentTypeTechnicalName]?.formats ?? [];

            return [...formats]
                .sort((left, right) => this.getFileFormatPriority(left) - this.getFileFormatPriority(right))
                .map((format) => {
                    return {
                        label: this.translateFileFormat(format),
                        value: format,
                    };
                });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.feature.isActive('DOCUMENT_GENERATION_REWORK') || !this.documentV2ApiService) {
                return;
            }

            this.documentV2ApiService.getAvailableTypes().then((response) => {
                this.supportedDocumentTypes = response.documentTypes ?? {};
            });
        },

        // TODO get from service
        getFileFormatPriority(fileFormat) {
            const priority = FILE_FORMAT_PRIORITY.indexOf(fileFormat);

            return priority === -1 ? Number.MAX_SAFE_INTEGER : priority;
        },

        // TODO get from service
        translateFileFormat(format) {
            const translationKey = {
                html: 'sw-bulk-edit.order.documents.generateInvoice.fileFormats.html',
                pdf: 'sw-bulk-edit.order.documents.generateInvoice.fileFormats.pdf',
                zugferd_embedded_pdf: 'sw-bulk-edit.order.documents.generateInvoice.fileFormats.zugferdEmbeddedPdf',
                zugferd_xml: 'sw-bulk-edit.order.documents.generateInvoice.fileFormats.zugferdXml',
            }[format];

            return translationKey ? this.$t(translationKey) : format;
        },
    },
};
