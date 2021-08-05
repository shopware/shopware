import template from './sw-product-stream-modal-preview.html.twig';
import './sw-product-stream-modal-preview.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-stream-modal-preview', {
    template,

    inject: ['repositoryFactory'],

    props: {
        filters: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            products: [],
            systemCurrency: null,
            criteria: null,
            searchTerm: '',
            page: 1,
            total: false,
            limit: 25,
            isLoading: false,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product-stream.filter.values.product'),
                    type: 'text',
                    routerLink: 'sw.product.detail',
                }, {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-product-stream.filter.values.manufacturerId'),
                }, {
                    property: 'active',
                    label: this.$tc('sw-product-stream.filter.values.active'),
                    align: 'center',
                    type: 'bool',
                }, {
                    property: 'price',
                    label: this.$tc('sw-product-stream.filter.values.price'),
                }, {
                    property: 'stock',
                    label: this.$tc('sw-product-stream.filter.values.stock'),
                    align: 'right',
                },
            ];
        },
    },

    watch: {
        searchTerm() {
            this.page = 1;
            this.isLoading = true;
            this.loadEntityData()
                .then(() => {
                    this.isLoading = false;
                });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            return this.loadSystemCurrency()
                .then(this.loadEntityData())
                .then(() => {
                    this.isLoading = false;
                });
        },

        loadEntityData() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.term = this.searchTerm || null;
            criteria.filters = this.mapFiltersForSearch(this.filters);
            criteria.addAssociation('manufacturer');
            criteria.addAssociation('options.group');

            return this.productRepository.search(criteria, {
                ...Context.api,
                inheritance: true,
            }).then((products) => {
                this.products = products;
                this.total = products.total;
                this.criteria = products.criteria;
            });
        },

        loadSystemCurrency() {
            return this.currencyRepository
                .get(Shopware.Context.app.systemCurrencyId, Context.api)
                .then((systemCurrency) => {
                    this.systemCurrency = systemCurrency;
                });
        },

        mapFiltersForSearch(filters) {
            return filters.map((condition) => {
                const { field, type, operator, value, parameters, queries } = condition;
                const mappedQueries = this.mapFiltersForSearch(queries);

                return { field, type, operator, value, parameters, queries: mappedQueries };
            });
        },

        closeModal() {
            this.$emit('modal-close');
        },

        getPriceForDefaultCurrency(product, currency) {
            return product.price.find((productPrice) => {
                return productPrice.currencyId === currency.id;
            });
        },

        onPageChange({ page = 1, limit = 25 }) {
            this.isLoading = true;

            this.page = page;
            this.limit = limit;

            this.loadEntityData()
                .then(() => {
                    this.isLoading = false;
                });
        },
    },
});
