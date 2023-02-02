import template from './sw-order-select-document-type-modal.html.twig';
import './sw-order-select-document-type-modal.scss';

/**
 * @package customer-order
 */

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        order: {
            type: Object,
            required: true,
        },

        value: {
            required: false,
            type: Object,
            default: null,
        },
    },

    data() {
        return {
            documentTypes: [],
            documentTypeCollection: null,
            documentType: null,
            invoiceExists: false,
            isLoading: false,
        };
    },

    computed: {
        creditItems() {
            const items = [];

            this.order.lineItems.forEach((lineItem) => {
                if (lineItem.type === 'credit') {
                    items.push(lineItem);
                }
            });

            return items;
        },

        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            return (new Criteria(1, 100))
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        documentCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('order.id', this.order.id));
            criteria.addFilter(Criteria.equals('order.versionId', this.order.versionId));
            criteria.addFilter(Criteria.equals('documentType.technicalName', 'invoice'));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.documentRepository.searchIds(this.documentCriteria).then((documentCollection) => {
                this.invoiceExists = documentCollection.total > 0;

                this.documentTypeRepository.search(this.documentTypeCriteria).then((response) => {
                    this.documentTypeCollection = response;
                    this.documentTypes = response.map((documentType) => {
                        const option = {
                            value: documentType.id,
                            name: documentType.name,
                            disabled: !this.documentTypeAvailable(documentType),
                        };

                        if (documentType.technicalName === 'storno' || documentType.technicalName === 'credit_note') {
                            return this.addHelpTextToOption(option, documentType);
                        }

                        return option;
                    });

                    if (this.documentTypes.length) {
                        this.documentType = this.documentTypes.find(documentType => !documentType.disabled).value;
                        this.onRadioFieldChange();
                    }

                    this.isLoading = false;
                });
            });
        },

        documentTypeAvailable(documentType) {
            return (
                (
                    documentType.technicalName !== 'storno' &&
                    documentType.technicalName !== 'credit_note'
                ) ||
                (
                    (
                        documentType.technicalName === 'storno' ||
                        (
                            documentType.technicalName === 'credit_note' &&
                            this.creditItems.length !== 0
                        )
                    ) && this.invoiceExists
                )
            );
        },

        addHelpTextToOption(option, documentType) {
            option.helpText = this.$tc(`sw-order.components.selectDocumentTypeModal.helpText.${documentType.technicalName}`);

            return option;
        },

        onRadioFieldChange() {
            this.$emit('change', this.documentTypeCollection.get(this.documentType));
        },
    },
};
