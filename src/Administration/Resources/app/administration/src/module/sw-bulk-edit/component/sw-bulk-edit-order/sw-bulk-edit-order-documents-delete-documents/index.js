import template from './sw-bulk-edit-order-documents-delete-documents.html.twig';

const { Criteria } = Shopware.Data;

/**
 * @sw-package after-sales
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
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

            return criteria;
        },

        documentTypes: {
            get() {
                return Shopware.Store.get('swBulkEdit')?.orderDocuments?.delete?.value;
            },
            set(documentTypes) {
                Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
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
        createdComponent() {
            this.isLoading = true;
            this.documentTypeRepository
                .search(this.documentTypeCriteria)
                .then((documentTypes) => {
                    documentTypes.forEach((documentType) => {
                        documentType.selected = false;
                    });
                    this.documentTypes = documentTypes;
                })
                .catch((error) => {
                    this.documentTypes = [];
                    this.createNotificationError({
                        message: error.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
};
