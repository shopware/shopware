/*
 * @package services-settings
 */

import template from './sw-product-stream-modal-preview.html.twig';
import './sw-product-stream-modal-preview.scss';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;
const PRODUCT_COMPARISON_SALES_CHANNEL_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';

/**
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'productStreamPreviewService',
    ],

    emits: ['modal-close'],

    props: {
        filters: {
            type: Array,
            required: true,
        },
        defaultLimit: {
            type: Number,
            default: 25,
        },
        defaultSorting: {
            type: String,
            default: null,
            validator(value) {
                return value === null || value.split(':').length === 2;
            },
        },
    },
    data() {
        return {
            products: [],
            selectedSalesChannel: null,
            searchTerm: '',
            page: 1,
            total: false,
            limit: this.defaultLimit,
            sorting: this.defaultSorting,
            isLoading: false,
            selectedCurrencyIsoCode: 'EUR',
            selectedCurrencyId: Context.app.systemCurrencyId,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            return new Criteria(1, 1)
                .addFilter(
                    Criteria.not('OR', [
                        Criteria.equals('typeId', PRODUCT_COMPARISON_SALES_CHANNEL_TYPE_ID),
                    ]),
                )
                .addSorting(Criteria.sort('type.iconName', 'ASC'));
        },

        previewCriteria() {
            const criteria = new Criteria(this.page, this.limit).setTerm(this.searchTerm);

            if (this.sorting) {
                const [
                    field,
                    direction,
                ] = this.sorting.split(':');
                criteria.addSorting(Criteria.sort(field, direction));
            }

            return criteria;
        },

        previewSelectionCriteria() {
            return new Criteria()
                .addFilter(
                    Criteria.not('OR', [
                        Criteria.equals('typeId', PRODUCT_COMPARISON_SALES_CHANNEL_TYPE_ID),
                    ]),
                )
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product-stream.filter.values.product'),
                    type: 'text',
                    routerLink: 'sw.product.detail',
                },
                {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-product-stream.filter.values.manufacturerId'),
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

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        stockColorVariantFilter() {
            return Shopware.Filter.getByName('stockColorVariant');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
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

        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.isLoading = true;
            this.loadEntityData().finally(() => {
                this.isLoading = false;
            });
        },

        onSalesChannelChange() {
            this.page = 1;
            this.isLoading = true;
            this.loadSalesChannelById()
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

            return this.productStreamPreviewService
                .preview(this.selectedSalesChannel, this.previewCriteria, this.mapFiltersForSearch(this.filters), {
                    'sw-currency-id': this.selectedCurrencyId,
                    'sw-inheritance': true,
                })
                .then((result) => {
                    this.products = Object.values(result.elements);
                    this.total = result.total;
                });
        },

        loadSalesChannels() {
            return this.salesChannelRepository.searchIds(this.salesChannelCriteria).then(({ data }) => {
                this.selectedSalesChannel = data.at(0);
            });
        },

        mapFiltersForSearch(filters = [], parentType = null) {
            return filters.map((condition) => {
                const { field, type, operator, value, parameters, queries } = condition;
                const mappedQueries = this.mapFiltersForSearch(queries, type);
                const mapped = {
                    field,
                    type,
                    operator,
                    value,
                    parameters,
                    queries: mappedQueries,
                };

                if (field === 'id' || field === 'product.id') {
                    const newOperator = this.isNotEqualToAnyType(type, parentType) ? 'AND' : 'OR';

                    return {
                        type: 'multi',
                        field: null,
                        operator: newOperator,
                        value: null,
                        parameters: null,
                        queries: [
                            mapped,
                            { ...mapped, ...{ field: 'parentId' } },
                        ],
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

            this.loadEntityData().finally(() => {
                this.isLoading = false;
            });
        },

        loadSalesChannelById() {
            if (this.selectedSalesChannel === null) {
                return Promise.resolve();
            }

            const criteria = this.salesChannelCriteria;

            criteria.addAssociation('currency');

            return this.salesChannelRepository
                .get(this.selectedSalesChannel, Shopware.Context.api, this.salesChannelCriteria)
                .then((salesChannel) => {
                    this.selectedCurrencyIsoCode = salesChannel.currency.isoCode;
                    this.selectedCurrencyId = salesChannel.currencyId;
                });
        },

        isNotEqualToAnyType(type, parentType) {
            return type === 'equalsAny' && parentType === 'not';
        },
    },
};
