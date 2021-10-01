import template from './sw-product-stream-grid-preview.html.twig';
import './sw-product-stream-grid-preview.scss';

const { Component, Context, Feature } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-stream-grid-preview', {
    template,

    inject: ['repositoryFactory'],

    props: {
        /**
         * The apiFilter of a loaded product stream
         */
        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        filters: {
            required: true,
        },
        columns: {
            required: false,
            type: Array,
            default() {
                return [];
            },
        },
        criteria: {
            required: false,
            type: Object,
            default() {
                return new Criteria(1, 10);
            },
        },
        showSelection: {
            required: false,
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            products: [],
            systemCurrency: null,
            searchTerm: '',
            page: 1,
            total: 0,
            limit: 10,
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

        defaultColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-product-stream.filter.values.product'),
                type: 'text',
                routerLink: 'sw.product.detail',
            }, {
                property: 'manufacturer.name',
                label: this.$tc('sw-product-stream.filter.values.manufacturer'),
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
            }];
        },

        productColumns() {
            if (this.columns.length) {
                return this.columns;
            }

            return this.defaultColumns;
        },

        emptyStateMessage() {
            if (!this.filters) {
                return this.$tc('global.entity-components.productStreamPreview.emptyMessageNoStream');
            }

            if (this.searchTerm.length) {
                return this.$tc('global.entity-components.productStreamPreview.emptyMessageNoSearchResults',
                    this.searchTerm,
                    {
                        term: this.searchTerm,
                    });
            }

            return this.$tc('global.entity-components.productStreamPreview.emptyMessageNoProducts');
        },
    },

    watch: {
        /* @deprecated tag:v6.5.0 watcher not debounced anymore, use `@search-term-change` event */
        searchTerm() {
            if (!Feature.isActive('FEATURE_NEXT_16271')) {
                this.page = 1;
                this.loadProducts();
            }
        },

        async filters(filtersValue) {
            if (!filtersValue) {
                this.total = 0;
                return;
            }

            this.isLoading = true;
            this.systemCurrency = await this.loadSystemDefaultCurrency();
            this.loadProducts();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onSearchTermChange() {
            if (Feature.isActive('FEATURE_NEXT_16271')) {
                this.page = 1;
                this.loadProducts();
            }
        },
        async createdComponent() {
            if (!this.filters) {
                return;
            }

            this.isLoading = true;
            this.systemCurrency = await this.loadSystemDefaultCurrency();
            this.loadProducts();
        },

        loadSystemDefaultCurrency() {
            return this.currencyRepository.get(Context.app.systemCurrencyId, Context.api);
        },

        loadProducts() {
            this.criteria.term = this.searchTerm || null;
            this.criteria.filters = [...this.filters];
            this.criteria.limit = this.limit;
            this.criteria.setPage(this.page);
            this.criteria.addAssociation('manufacturer');
            this.criteria.addAssociation('options.group');
            this.criteria.addGroupField('displayGroup');
            this.criteria.addFilter(
                Criteria.not(
                    'AND',
                    [
                        Criteria.equals('displayGroup', null),
                    ],
                ),
            );

            return this.productRepository.search(this.criteria, {
                ...Context.api,
                inheritance: true,
            }).then((products) => {
                this.products = products;
                this.total = products.total;

                this.isLoading = false;
            });
        },

        onPageChange({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;
            this.isLoading = true;

            this.loadProducts();
        },

        getPriceForDefaultCurrency(product) {
            const price = product.price.find((productPrice) => {
                return productPrice.currencyId === this.systemCurrency.id;
            });

            return price ? price.gross : '-';
        },

        onSelectionChange(products) {
            this.$emit('selection-change', products);
        },
    },
});
