import template from './sw-cms-el-config-product-listing-config-sorting-grid.html.twig';
import './sw-cms-el-config-product-listing-config-sorting-grid.scss';

Shopware.Component.register('sw-cms-el-config-product-listing-config-sorting-grid', {
    template,

    props: {
        productSortings: {
            type: Array,
            required: true
        },
        defaultSorting: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            paginationLimit: 10,
            paginationPage: 1,
            paginationSteps: [10]
        };
    },

    methods: {
        formatProductSortingFields(fields) {
            const fieldNames = fields.map(currentField => {
                return currentField.field;
            });

            return fieldNames.join(', ');
        },

        onDelete(productSorting) {
            this.productSortings.remove(productSorting.id);
        },

        isDefaultSorting(productSorting) {
            if (!this.defaultSorting) {
                return false;
            }

            return productSorting.id === this.defaultSorting.id;
        }
    },

    computed: {
        paginationTotal() {
            return this.productSortings.length;
        },

        gridColumns() {
            return [
                {
                    property: 'label',
                    label: 'sw-cms.elements.productListing.config.sorting.gridHeaderName'
                },
                {
                    property: 'fields',
                    label: 'sw-cms.elements.productListing.config.sorting.gridHeaderFields',
                    multiLine: true
                },
                {
                    property: 'priority',
                    label: 'sw-cms.elements.productListing.config.sorting.gridHeaderPriority',
                    inlineEdit: 'number'
                }
            ];
        }
    }
});
