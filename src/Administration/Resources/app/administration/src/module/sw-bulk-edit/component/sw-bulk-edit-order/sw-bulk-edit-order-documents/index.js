/**
 * @package system-settings
 */
import template from './sw-bulk-edit-order-documents.html.twig';
import './sw-bulk-edit-order-documents.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

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

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.documentTypeRepository.search(this.documentTypeCriteria).then((res) => {
                this.documentTypes = res;

                this.documentTypes.forEach(type => {
                    this.value.documentType[type.technicalName] = null;
                });
            });
        },
    },
};
