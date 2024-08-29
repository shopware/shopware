import template from './sw-cms-el-config-product-listing-config-sorting-grid.html.twig';
import './sw-cms-el-config-product-listing-config-sorting-grid.scss';

const { Criteria } = Shopware.Data;

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    mixins: [
        'sw-inline-snippet',
    ],

    props: {
        productSortings: {
            type: Array,
            required: true,
        },
        defaultSorting: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            limit: 10,
            page: 1,
            customFields: [],
        };
    },

    computed: {
        visibleProductSortings() {
            return this.productSortings.slice((this.page - 1) * this.limit, (this.page - 1) * this.limit + this.limit);
        },

        paginationVisible() {
            return this.total > this.limit;
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        customFieldCriteria() {
            return new Criteria(1, 25);
        },

        total() {
            return this.productSortings.length;
        },

        gridColumns() {
            return [
                {
                    property: 'label',
                    label: 'sw-cms.elements.productListing.config.sorting.gridHeaderName',
                },
                {
                    property: 'fields',
                    label: 'sw-cms.elements.productListing.config.sorting.gridHeaderFields',
                    multiLine: true,
                },
                {
                    property: 'priority',
                    label: 'sw-cms.elements.productListing.config.sorting.gridHeaderPriority',
                    inlineEdit: 'number',
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCustomFields();
        },

        fetchCustomFields() {
            this.customFieldRepository.search(this.customFieldCriteria).then(response => {
                this.customFields = response;
            });
        },

        formatProductSortingFields(fields) {
            if (!Array.isArray(fields)) {
                return '';
            }

            const labels = fields.map(currentField => {
                if (this.isItemACustomField(currentField.field)) {
                    return this.getCustomFieldLabelByCriteriaName(currentField.field);
                }

                return this.$tc(
                    `sw-settings-listing.general.productSortingCriteriaGrid.options.label.${currentField.field}`,
                );
            });

            return labels.join(', ');
        },

        isItemACustomField(fieldName) {
            const strippedFieldName = this.stripCustomFieldPath(fieldName);

            return this.customFields.some(currentCustomField => {
                return currentCustomField.name === strippedFieldName;
            });
        },

        stripCustomFieldPath(fieldName) {
            return fieldName.replace(/customFields\./, '');
        },

        getCustomFieldLabelByCriteriaName(criteriaName) {
            const technicalName = this.stripCustomFieldPath(criteriaName);
            const customField = this.getCustomFieldByName(technicalName);

            const inlineSnippet = this.getInlineSnippet(customField.config.label);

            if (inlineSnippet === null) {
                return technicalName;
            }

            return inlineSnippet;
        },

        getCustomFieldByName(technicalName) {
            return this.customFields.find(currentCustomField => {
                return currentCustomField.name === technicalName;
            });
        },

        onDelete(productSorting) {
            this.productSortings.remove(productSorting.id);
        },

        isDefaultSorting(productSorting) {
            if (!this.defaultSorting) {
                return false;
            }

            return productSorting.id === this.defaultSorting.id;
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
        },
    },
};
