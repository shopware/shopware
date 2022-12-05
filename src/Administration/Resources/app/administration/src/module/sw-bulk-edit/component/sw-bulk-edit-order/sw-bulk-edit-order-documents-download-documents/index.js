/**
 * @package system-settings
 */
import template from './sw-bulk-edit-order-documents-download-documents.html.twig';
import './sw-bulk-edit-order-documents-download-documents.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

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
                return Shopware.State.get('swBulkEdit')?.orderDocuments?.download?.value;
            },
            set(documentTypes) {
                Shopware.State.commit('swBulkEdit/setOrderDocumentsValue', {
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
        createdComponent() {
            this.getDocumentTypes()
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
                });
        },

        getDocumentTypes() {
            return this.documentTypeRepository.search(this.documentTypeCriteria);
        },
    },
};
