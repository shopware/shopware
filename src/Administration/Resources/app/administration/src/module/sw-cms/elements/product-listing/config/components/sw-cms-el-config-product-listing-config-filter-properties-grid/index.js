import template from './sw-cms-el-config-product-listing-config-filter-properties-grid.html.twig';
import './sw-cms-el-config-product-listing-config-filter-properties-grid.scss';

Shopware.Component.register('sw-cms-el-config-product-listing-config-filter-properties-grid', {
    template,

    props: {
        properties: {
            type: Array,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            limit: 6,
            page: 1,
        };
    },

    computed: {
        componentClasses() {
            return {
                'is--disabled': this.disabled,
            };
        },

        visibleProperties() {
            return this.properties.slice((this.page - 1) * this.limit, (this.page - 1) * this.limit + this.limit);
        },

        paginationVisible() {
            return this.total > this.limit;
        },

        total() {
            return this.properties.length;
        },

        gridColumns() {
            return [
                {
                    property: 'status',
                    label: 'sw-cms.elements.productListing.config.filter.gridHeaderStatus',
                    disabled: this.disabled,
                    width: '70px',
                },
                {
                    property: 'name',
                    label: 'sw-cms.elements.productListing.config.filter.gridHeaderName',
                },
            ];
        },
    },

    methods: {
        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.$emit('page-change');
        },

        onChangePropertyStatus(item) {
            this.$emit('property-status-changed', item.id);
        },
    },
});
