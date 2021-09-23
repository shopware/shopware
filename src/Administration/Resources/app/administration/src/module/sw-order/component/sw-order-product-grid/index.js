import template from './sw-order-product-grid.html.twig';
import './sw-order-product-grid.scss';

const { Component, Mixin, Service } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();

Component.register('sw-order-product-grid', {
    template,

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            products: null,
            isLoading: false,
            disableRouteParams: true,
            modifiedProducts: [],
        };
    },

    computed: {
        productRepository() {
            return Service('repositoryFactory').create('product');
        },

        currencyRepository() {
            return Service('repositoryFactory').create('currency');
        },

        productCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('options.group');

            if (this.term) {
                criteria.setTerm(this.term);
            }

            criteria.addFilter(
                Criteria.multi(
                    'OR',
                    [
                        Criteria.equals('product.childCount', 0),
                        Criteria.equals('product.childCount', null),
                    ],
                ),
            );

            criteria.addFilter(
                Criteria.equals('product.visibilities.salesChannelId', this.customer?.salesChannelId),
            );
            return criteria;
        },

        taxStatus() {
            return this.cart?.price?.taxStatus;
        },

        productColumns() {
            return [
                {
                    property: 'amount',
                    label: this.$tc('sw-order.createModal.products.columnAmount'),
                    allowResize: false,
                    width: '60px',
                    sortable: false,
                    primary: true,
                },
                {
                    property: 'name',
                    label: this.$tc('sw-order.createModal.products.columnProductName'),
                    allowResize: false,
                    primary: true,
                    width: '200px',
                },
                {
                    property: 'productNumber',
                    label: this.$tc('sw-order.createModal.products.columnProductNumber'),
                    allowResize: true,
                },
                {
                    property: 'price',
                    dataIndex: 'price',
                    label: this.taxStatus === 'gross' ?
                        this.$tc('sw-order.createModal.products.columnPriceGross') :
                        this.$tc('sw-order.createModal.products.columnPriceNet'),
                    allowResize: false,
                    align: 'right',
                    width: '60px',
                },
            ];
        },

        ...mapState('swOrder', ['customer', 'currency', 'cart']),
    },

    watch: {
        'customer.salesChannelId'() {
            this.getList();
        },
    },

    methods: {
        getList() {
            if (!this.customer?.salesChannelId) {
                return null;
            }

            this.isLoading = true;
            return this.productRepository.search(this.productCriteria,
                {
                    ...Shopware.Context.api,
                    inheritance: true,
                })
                .then((products) => {
                    this.products = this.generateProducts(products);
                    this.total = products.total;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        generateProducts(products) {
            if (!this.modifiedProducts.length) {
                return products;
            }

            products.forEach(product => {
                const existedItem = this.modifiedProducts.find(selectedProduct => selectedProduct.id === product.id);

                if (existedItem) {
                    product.amount = existedItem.amount;
                }
            });

            return products;
        },

        onSelectionChange(selection) {
            this.$emit('selection-change', Object.values(selection));
        },

        onSearch(value) {
            this.storeModifiedProducts();

            if (value.length === 0) {
                value = undefined;
            }

            this.term = value;
            this.page = 1;
            this.getList();
        },

        storeModifiedProducts() {
            this.products.forEach(product => {
                const existIndex = this.modifiedProducts.findIndex(item => item.id === product.id);

                if (existIndex >= 0) {
                    this.modifiedProducts[existIndex].amount = product.amount;
                    return;
                }

                if (!product.amount) {
                    return;
                }

                this.modifiedProducts.push({
                    id: product.id,
                    amount: product.amount,
                });
            });
        },

        onPageChange(opts) {
            this.storeModifiedProducts();

            this.page = opts.page;
            this.limit = opts.limit;

            this.getList();
        },
    },
});
