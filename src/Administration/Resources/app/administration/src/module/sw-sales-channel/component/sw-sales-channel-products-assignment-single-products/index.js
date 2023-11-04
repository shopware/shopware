/**
 * @package sales-channel
 */

import template from './sw-sales-channel-products-assignment-single-products.html.twig';
import './sw-sales-channel-products-assignment-single-products.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-sales-channel-products-assignment-single-products', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        containerStyle: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            products: [],
            searchTerm: null,
            isLoading: false,
            page: 1,
            limit: 25,
            total: 0,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria.addAssociation('visibilities.salesChannel');
            criteria.addFilter(Criteria.not('and', [
                Criteria.equals('product.visibilities.salesChannelId', this.salesChannel.id),
            ]));
            criteria.addFilter(Criteria.equals('parentId', null));

            return criteria;
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-sales-channel.detail.products.columnProductName'),
                    allowResize: true,
                },
                {
                    property: 'productNumber',
                    label: this.$tc('sw-sales-channel.detail.products.columnProductNumber'),
                    allowResize: true,
                },
            ];
        },
    },

    created() {
        this.getProducts();
    },

    methods: {
        getProducts() {
            this.isLoading = true;

            return this.productRepository.search(this.productCriteria)
                .then((products) => {
                    this.products = products;
                    this.total = products.total;
                })
                .catch((error) => {
                    this.products = [];
                    this.total = 0;
                    this.createNotificationError({
                        message: error.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;

            if (searchTerm) {
                this.page = 1;
            }

            this.getProducts();
        },

        onSelectionChange(selection) {
            const products = Object.values(selection);
            this.$emit('selection-change', products, 'singleProducts');
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.products.criteria.sortings.forEach(({ field, naturalSorting, order }) => {
                this.productCriteria.addSorting(
                    Criteria.sort(field, order, naturalSorting),
                );
            });

            this.getProducts();
        },
    },
});
