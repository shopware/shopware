import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-stream-modal-preview.html.twig';
import './sw-product-stream-modal-preview.scss';

Component.register('sw-product-stream-modal-preview', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],
    props: {
        productFilter: {
            type: Object
        }
    },
    data() {
        return {
            products: [],
            criteria: '',
            total: false,
            isLoading: false,
            disableRouteParams: true
        };
    },
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    filters: {
        stockColorVariant(value) {
            if (value > 25) {
                return 'success';
            }
            if (value < 25 && value > 0) {
                return 'warning';
            }

            return 'error';
        }
    },
    created() {
        this.onCreated();
    },
    beforeDestroy() {
        this.onDestroy();
    },
    methods: {
        onCreated() {
            this.getList();
        },
        onDestroy() {
            this.$emit('sw-product-stream-modal-preview-destroy');
        },
        getList() {
            this.isLoading = true;
            if (!this.criteria) {
                this.buildCriteria();
            }
            const params = this.getListingParams();
            params.term = this.searchTerm;
            params.criteria = this.criteria;

            this.products = [];

            return this.productStore.getList(params).then((response) => {
                this.total = response.total;
                this.products = response.items;
                this.isLoading = false;
                return this.products;
            }).catch(() => {
                this.total = 0;
                this.products = [];
                this.isLoading = false;
            });
        },
        buildCriteria() {
            if (this.productFilter) {
                this.criteria = this.handleFilter(this.productFilter);
            }
        },
        handleFilter(filter) {
            if (filter.type === 'multi' || filter.type === 'not') {
                return this.buildMultiFilter(filter.operator, filter.queries, filter.type);
            }
            if (filter.type === 'range') {
                return CriteriaFactory.range(filter.field, filter.parameters);
            }
            if (filter.type === 'equals') {
                return CriteriaFactory.equals(filter.field, filter.value);
            }
            if (filter.type === 'equalsAny') {
                return CriteriaFactory.equalsAny(filter.field, filter.value.split('|'));
            }
            if (filter.type === 'contains') {
                return CriteriaFactory.contains(filter.field, filter.value);
            }
            return null;
        },
        buildMultiFilter(operator, filters, type) {
            if ((!operator && type !== 'not') || filters.length === 0) {
                return null;
            }
            const handledFilters = [];
            filters.forEach((filter) => {
                const handledFilter = this.handleFilter(filter);
                if (!handledFilter) {
                    return;
                }
                handledFilters.push(handledFilter);
            });
            if (type === 'not') {
                return CriteriaFactory.not(operator || 'AND', ...handledFilters);
            }
            return CriteriaFactory.multi(operator, ...handledFilters);
        },
        closeModal() {
            this.$emit('sw-product-stream-modal-preview-close');
        },
        searchTermChanged(term) {
            this.searchTerm = String(term);
            this.page = 1;
            this.getList();
        }
    }
});
