import template from './sw-product-stream-grid-preview.html.twig';
import './sw-product-stream-grid-preview.scss';

const { Component, Context, Defaults } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-product-stream-grid-preview', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'productStreamPreviewService',
    ],

    emits: ['selection-change'],

    props: {
        /**
         * The apiFilter of a loaded product stream
         */
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

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            return new Criteria(1, 1)
                .addFilter(
                    Criteria.not('OR', [
                        Criteria.equals('typeId', Defaults.productComparisonTypeId),
                    ]),
                )
                .addSorting(Criteria.sort('type.iconName', 'ASC'));
        },

        defaultColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product-stream.filter.values.product'),
                    type: 'text',
                    routerLink: 'sw.product.detail',
                },
                {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-product-stream.filter.values.manufacturer'),
                },
                {
                    property: 'active',
                    label: this.$tc('sw-product-stream.filter.values.active'),
                    align: 'center',
                    type: 'bool',
                },
                {
                    property: 'price',
                    label: this.$tc('sw-product-stream.filter.values.price'),
                },
                {
                    property: 'stock',
                    label: this.$tc('sw-product-stream.filter.values.stock'),
                    align: 'right',
                },
            ];
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
                return this.$tc(
                    'global.entity-components.productStreamPreview.emptyMessageNoSearchResults',
                    this.searchTerm,
                    {
                        term: this.searchTerm,
                    },
                );
            }

            return this.$tc('global.entity-components.productStreamPreview.emptyMessageNoProducts');
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },

    watch: {
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
        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.loadProducts();
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
            // eslint-disable-next-line vue/no-mutating-props
            this.criteria.term = this.searchTerm || null;
            // eslint-disable-next-line vue/no-mutating-props
            this.criteria.filters = [...this.filters];
            // eslint-disable-next-line vue/no-mutating-props
            this.criteria.limit = this.limit;
            this.criteria.setPage(this.page);
            this.criteria.addAssociation('manufacturer');
            this.criteria.addAssociation('options.group');
            this.criteria.addGroupField('displayGroup');
            this.criteria.addFilter(
                Criteria.not('AND', [
                    Criteria.equals('displayGroup', null),
                ]),
            );

            return this.salesChannelRepository
                .searchIds(this.salesChannelCriteria)
                .then(({ data }) => {
                    return this.productStreamPreviewService.preview(data.at(0), this.criteria, [], {
                        'sw-currency-id': Context.app.systemCurrencyId,
                        'sw-inheritance': true,
                    });
                })
                .then((result) => {
                    this.products = Object.values(result.elements);
                    this.total = result.total;
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
