/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-download-documents.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        repositoryFactory: {},
        feature: {},
        documentV2Service: {},
    },

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    computed: {
        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        documentTypes: {
            get() {
                return Shopware.Store.get('swBulkEdit')?.orderDocuments?.download?.value;
            },
            set(documentTypes) {
                Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
                    type: 'download',
                    value: documentTypes,
                });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            try {
                if (this.feature.isActive('DOCUMENT_GENERATION_REWORK') && this.documentV2Service) {
                    const supportedDocumentTypes = await this.documentV2Service.getAvailableDocumentTypes();

                    this.documentTypes = Object.keys(supportedDocumentTypes).map((technicalName) => {
                        return {
                            id: technicalName,
                            technicalName,
                            name: this.$t(this.documentV2Service.getDocumentTypeSnippet(technicalName)),
                        };
                    });
                    this.documentTypes.total = this.documentTypes.length;
                } else {
                    this.documentTypes = await this.getDocumentTypes();
                }

                this.documentTypes.forEach((documentType) => {
                    documentType.selected = false;
                });
            } catch (error) {
                this.documentTypes = [];
                this.createNotificationError({
                    message: error.message,
                });
            }
        },

        getDocumentTypes() {
            return this.documentTypeRepository.search(this.documentTypeCriteria);
        },
    },
};
