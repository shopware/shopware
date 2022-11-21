import template from './sw-order-product-grid.html.twig';
import './sw-order-product-grid.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    props: {
        salesChannelId: {
            type: String,
            required: true,
        },

        currency: {
            type: Object,
            required: true,
        },

        taxStatus: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            products: null,
            isLoading: false,
            disableRouteParams: true,
            modifiedProducts: [],
            selection: [],
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
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
                Criteria.equals('product.visibilities.salesChannelId', this.salesChannelId),
            );
            return criteria;
        },

        emptyTitle() {
            if (!this.term) {
                return this.$tc('sw-product.list.messageEmpty');
            }

            return this.$tc('sw-order.itemModal.productGrid.textEmptySearch', 0, { name: this.term });
        },

        productColumns() {
            return [
                {
                    property: 'amount',
                    label: this.$tc('sw-order.itemModal.productGrid.columnAmount'),
                    allowResize: false,
                    width: '154px',
                    sortable: false,
                    primary: true,
                },
                {
                    property: 'name',
                    label: this.$tc('sw-order.itemModal.productGrid.columnProductName'),
                    allowResize: false,
                    primary: true,
                    width: '200px',
                },
                {
                    property: 'productNumber',
                    label: this.$tc('sw-order.itemModal.productGrid.columnProductNumber'),
                    allowResize: true,
                },
                {
                    property: 'price',
                    dataIndex: 'price',
                    label: this.priceLabel,
                    allowResize: false,
                    align: 'right',
                    width: '60px',
                },
            ];
        },

        priceLabel() {
            return this.taxStatus === 'gross'
                ? this.$tc('sw-order.createBase.columnPriceGross')
                : this.$tc('sw-order.createBase.columnPriceNet');
        },
    },

    watch: {
        salesChannelId() {
            this.getList();
        },
    },

    methods: {
        getList() {
            if (!this.salesChannelId) {
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

        /**
        *  To get amount of product correctly when user navigate to another page or search products
        */
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
            this.selection = Object.values(selection);
            this.$emit('selection-change', Object.values(selection));
        },

        onSelectItem(_, item, selected) {
            this.$set(item, 'amount', selected ? 1 : null);
        },

        onSearch(value) {
            if (value.length === 0) {
                value = undefined;
            }

            this.term = value;
            this.page = 1;
            this.getList();
        },

        onPageChange(opts) {
            this.page = opts.page;
            this.limit = opts.limit;

            this.getList();
        },

        getProductPrice(item) {
            return this.taxStatus === 'gross'
                ? item?.price[0]?.gross
                : item?.price[0]?.net;
        },

        changeProductAmount(product) {
            if (this.$refs.orderProductGrid?.selection) {
                this.$set(this.$refs.orderProductGrid.selection, product.id, product);
            }

            this.updateModifiedProducts(product);
            this.updateSelection(product);
        },

        updateModifiedProducts(product) {
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
        },

        updateSelection(product) {
            const existIndex = this.selection.findIndex(item => item.id === product.id);

            if (existIndex < 0) {
                return;
            }

            this.selection[existIndex].amount = product.amount;
            this.$emit('selection-change', this.selection);
        },
    },
};
