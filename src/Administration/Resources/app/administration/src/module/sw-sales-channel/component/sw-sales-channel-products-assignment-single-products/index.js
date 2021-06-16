import template from './sw-sales-channel-products-assignment-single-products.html.twig';
import './sw-sales-channel-products-assignment-single-products.scss';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

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
    },

    data() {
        return {
            products: [],
            searchTerm: null,
            skeletonItemAmount: 25,
            isLoading: false,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productCriteria() {
            const criteria = new Criteria();

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
                    primary: true,
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
            return this.productRepository.search(this.productCriteria, Context.api)
                .then((products) => {
                    this.products = products;
                })
                .catch(err => {
                    this.products = [];
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;
            this.getProducts();
        },

        onSelectionChange(selection) {
            this.$emit('selection-change', selection, 'singleProducts');
        },
    },
});
