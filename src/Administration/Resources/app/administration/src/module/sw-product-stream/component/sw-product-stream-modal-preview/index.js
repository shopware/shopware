/*
 * @package business-ops
 */

import template from './sw-product-stream-modal-preview.html.twig';
import './sw-product-stream-modal-preview.scss';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
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
            searchTerm: '',
            page: 1,
            total: false,
            limit: 25,
            isLoading: false,
        };
    },

    computed: {
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

    created() {
        this.createdComponent();
    },

    methods: {
        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.isLoading = true;
            this.loadEntityData()
                .then(() => {
                    this.isLoading = false;
                });
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
