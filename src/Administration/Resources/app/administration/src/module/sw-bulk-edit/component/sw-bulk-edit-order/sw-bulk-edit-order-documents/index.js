/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents.html.twig';
import './sw-bulk-edit-order-documents.scss';

const { Mixin } = Shopware;
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
        Mixin.getByName('notification'),
    ],

    props: {
        documents: {
            type: Object,
            required: true,
        },
        value: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            documentTypes: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            /** @deprecated tag:v6.9.0 - dropped this filter when document_type is removed. */
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('technicalName', 'app_provided')]));

            return criteria;
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
                            name: this.documentV2Service.getDocumentTypeLabel(
                                technicalName,
                                supportedDocumentTypes[technicalName]?.label,
                            ),
                        };
                    });
                } else {
                    this.documentTypes = await this.documentTypeRepository.search(this.documentTypeCriteria);
                }

                this.documentTypes.forEach((type) => {
                    this.value.documentType[type.technicalName] = null;
                });
            } catch (error) {
                this.documentTypes = [];
                this.createNotificationError({
                    message: error.message,
                });
            }
        },
    },
};
