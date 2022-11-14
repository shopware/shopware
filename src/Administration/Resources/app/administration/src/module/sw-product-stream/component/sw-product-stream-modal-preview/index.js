/*
 * @package inventory
 */

import template from './sw-product-stream-modal-preview.html.twig';
import './sw-product-stream-modal-preview.scss';

const { Context, Feature } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['repositoryFactory', 'productStreamPreviewService'],

    props: {
        filters: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            products: [],
            selectedSalesChannel: null,
            /* @deprecated tag:v6.5.0 - property systemCurrency will be removed */
            systemCurrency: null,
            /* @deprecated tag:v6.5.0 - property criteria will be removed */
            criteria: null,
            searchTerm: '',
            page: 1,
            total: false,
            limit: 25,
            isLoading: false,
        };
    },

    computed: {
        /* @deprecated tag:v6.5.0 - computed property productRepository will be removed */
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        /* @deprecated tag:v6.5.0 - computed property currencyRepository will be removed */
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addSorting(Criteria.sort('type.iconName', 'ASC'));

            return criteria;
        },

        previewCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.searchTerm);

            return criteria;
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
        /* @deprecated tag:v6.5.0 watcher not debounced anymore, use `@search-term-change` event */
        searchTerm() {
            if (!Feature.isActive('FEATURE_NEXT_16271')) {
                this.page = 1;
                this.isLoading = true;
                this.loadEntityData()
                    .then(() => {
                        this.isLoading = false;
                    });
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onSearchTermChange() {
            if (Feature.isActive('FEATURE_NEXT_16271')) {
                this.page = 1;
                this.isLoading = true;
                this.loadEntityData()
                    .then(() => {
                        this.isLoading = false;
                    });
            }
        },
        onSalesChannelChange() {
            this.page = 1;
            this.isLoading = true;
            this.loadEntityData()
                .then(() => {
                    this.isLoading = false;
                });
        },
        createdComponent() {
            this.isLoading = true;

            return this.loadSalesChannels()
                .then(() => {
                    return this.loadEntityData();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        loadEntityData() {
            if (!this.selectedSalesChannel) {
                return false;
            }

            return this.productStreamPreviewService.preview(
                this.selectedSalesChannel,
                this.previewCriteria,
                this.mapFiltersForSearch(this.filters),
                {
                    'sw-currency-id': Context.app.systemCurrencyId,
                    'sw-inheritance': true,
                },
            ).then((result) => {
                this.products = Object.values(result.elements);
                this.total = result.total;
            });
        },

        /* @deprecated tag:v6.5.0 - method loadSystemCurrency will be removed */
        loadSystemCurrency() {
            return this.currencyRepository
                .get(Shopware.Context.app.systemCurrencyId, Context.api)
                .then((systemCurrency) => {
                    this.systemCurrency = systemCurrency;
                });
        },

        loadSalesChannels() {
            return this.salesChannelRepository.searchIds(this.salesChannelCriteria).then(({ data }) => {
                this.selectedSalesChannel = data.at(0);
            });
        },

        mapFiltersForSearch(filters) {
            return filters.map((condition) => {
                const { field, type, operator, value, parameters, queries } = condition;
                const mappedQueries = this.mapFiltersForSearch(queries);
                const mapped = { field, type, operator, value, parameters, queries: mappedQueries };

                if (field === 'id' || field === 'product.id') {
                    return {
                        type: 'multi',
                        field: null,
                        operator: 'OR',
                        value: null,
                        parameters: null,
                        queries: [mapped, { ...mapped, ...{ field: 'parentId' } }],
                    };
                }

                return mapped;
            });
        },

        closeModal() {
            this.$emit('modal-close');
        },

        getPriceForDefaultCurrency(product) {
            const cheapest = product.calculatedCheapestPrice;
            let real = product.calculatedPrice;

            if (product.calculatedPrices.length > 0) {
                real = product.calculatedPrices[product.calculatedPrices.length - 1];
            }

            if (cheapest.unitPrice !== real.unitPrice) {
                return real;
            }

            return cheapest;
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
};
