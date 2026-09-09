import template from './sw-bulk-edit-order-documents-delete-documents.html.twig';
import { Criteria } from 'shopware:data';
import notificationMixin from 'shopware:mixins/notification';
import useSwBulkEditStore from 'shopware:stores/swBulkEdit';

/**
 * @sw-package after-sales
 * @private
 */
export default {
    template,

    inject: {
        repositoryFactory: {},
        feature: {},
        documentV2Service: {},
    },

    mixins: [
        notificationMixin,
    ],

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            /** @deprecated tag:v6.9.0 - drop this filter when document_type is removed. */
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('technicalName', 'app_provided')]));

            return criteria;
        },

        documentTypes: {
            get() {
                return useSwBulkEditStore()?.orderDocuments?.delete?.value;
            },
            set(documentTypes) {
                useSwBulkEditStore().setOrderDocumentsValue({
                    type: 'delete',
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
            this.isLoading = true;

            try {
                if (this.feature.isActive('DOCUMENT_GENERATION_REWORK') && this.documentV2Service) {
                    const supportedDocumentTypes = await this.documentV2Service.getAvailableDocumentTypes();

                    this.documentTypes = Object.keys(supportedDocumentTypes).map((technicalName) => {
                        return {
                            id: technicalName,
                            technicalName,
                            name: this.documentV2Service.getDocumentTypeLabel(
                                technicalName,
                                supportedDocumentTypes[technicalName]?.label,
                            ),
                        };
                    });
                    this.documentTypes.total = this.documentTypes.length;
                } else {
                    this.documentTypes = await this.documentTypeRepository.search(this.documentTypeCriteria);
                }

                this.documentTypes.forEach((documentType) => {
                    documentType.selected = false;
                });
            } catch (error) {
                this.documentTypes = [];
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
};
