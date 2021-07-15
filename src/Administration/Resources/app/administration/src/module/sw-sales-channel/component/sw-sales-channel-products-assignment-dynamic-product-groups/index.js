import template from './sw-sales-channel-products-assignment-dynamic-product-groups.html.twig';
import './sw-sales-channel-products-assignment-dynamic-product-groups.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-products-assignment-dynamic-product-groups', {
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
            productStreams: [],
            productStreamFilter: [],
            isProductStreamsLoading: false,
            isProductLoading: false,
            page: 1,
            limit: 5,
            total: 0,
            term: null,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        productCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.filters = this.productStreamFilter;
            criteria.addAssociation('visibilities.salesChannel');
            criteria.addFilter(Criteria.not('AND', [
                Criteria.equals('product.visibilities.salesChannelId', this.salesChannel.id),
            ]));

            return criteria;
        },

        productStreamCriteria() {
            const criteria = new Criteria();

            criteria.setPage(this.page);
            criteria.setLimit(this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        productStreamColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-sales-channel.detail.productAssignmentModal.dynamicProductGroups.columnName'),
                    sortable: false,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getProductStreams();
        },

        getProductStreams() {
            this.isProductStreamsLoading = true;

            return this.productStreamRepository.search(this.productStreamCriteria)
                .then((productStreams) => {
                    this.productStreams = productStreams;
                    this.total = productStreams.total;
                })
                .catch(() => {
                    this.productStreams = [];
                    this.total = 0;
                })
                .finally(() => {
                    this.isProductStreamsLoading = false;
                });
        },

        onSearch(term) {
            this.term = term;
            if (term) {
                this.page = 1;
            }

            this.getProductStreams();
        },

        onPaginate(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getProductStreams();
        },

        onOpen(productStream) {
            const route = this.$router.resolve({ name: 'sw.product.stream.detail', params: { id: productStream.id } });

            window.open(route.href, '_blank');
        },

        async onSelect(productStreams) {
            if (Object.keys(productStreams).length <= 0) {
                this.$emit('selection-change', [], 'groupProducts');
                return;
            }

            try {
                const products = await this.getProductsFromProductStreams(productStreams);
                this.$emit('selection-change', products, 'groupProducts');
            } catch (error) {
                this.createNotificationError({ message: error.message });
            }
        },

        getProductsFromProductStreams(productStreams) {
            const promises = Object.keys(productStreams).map((id) => {
                return this.getProductStreamFilter(id).then(() => this.getProducts());
            });

            this.$emit('product-loading', true);
            this.isProductLoading = true;

            return Promise.all(promises)
                .then((values) => {
                    const products = values.flat();
                    return products;
                })
                .finally(() => {
                    this.$emit('product-loading', false);
                    this.isProductLoading = false;
                });
        },

        getProductStreamFilter(id) {
            return this.productStreamRepository.get(id)
                .then((productStreamFilter) => {
                    this.productStreamFilter = productStreamFilter.apiFilter;
                })
                .catch((error) => {
                    this.productStreamFilter = [];
                    return error;
                });
        },

        getProducts() {
            return this.productRepository.search(this.productCriteria)
                .then((products) => {
                    return products;
                });
        },
    },
});
