/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-generate-invoice.html.twig';
import './sw-bulk-edit-order-documents-generate-invoice.scss';

const { Store } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        feature: {},
        documentV2Service: {
            default: null,
        },
    },

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

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

            return this.documentV2Service.sortFileFormats(formats).map((format) => {
                return {
                    label: this.$t(this.documentV2Service.getFileFormatSnippet(format)),
                    value: format,
                };
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            if (!this.feature.isActive('DOCUMENT_GENERATION_REWORK') || !this.documentV2Service) {
                return;
            }

            try {
                this.supportedDocumentTypes = await this.documentV2Service.getAvailableDocumentTypes();
            } catch (error) {
                this.supportedDocumentTypes = {};
                this.createNotificationError({
                    message: error.message,
                });
            }
        },
    },
};
