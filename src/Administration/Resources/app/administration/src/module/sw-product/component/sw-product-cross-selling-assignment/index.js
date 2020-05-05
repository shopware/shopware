import template from './sw-product-cross-selling-assignment.html.twig';
import './sw-product-cross-selling-assignment.scss';

const { mapGetters, mapState } = Shopware.Component.getComponentHelper();
const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-cross-selling-assignment', {
    template,

    inject: ['repositoryFactory'],

    props: {
        assignedProducts: {
            type: Array,
            required: true
        },

        crossSellingId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            isLoadingData: false
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        isLoadingGrid() {
            return this.isLoadingData || this.isLoading;
        },

        assignmentRepository() {
            return this.repositoryFactory.create(
                this.assignedProducts.entity,
                this.assignedProducts.source
            );
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        searchCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.product.id)]));
            return criteria;
        },

        searchContext() {
            return {
                ...Context.api,
                inheritance: true
            };
        },

        total() {
            if (!this.assignedProducts || !Array.isArray(this.assignedProducts)) {
                return 0;
            }

            return this.assignedProducts.length;
        },


        assignedProductColumns() {
            return [{
                property: 'product.translated.name',
                label: this.$tc('sw-product.list.columnName'),
                primary: true,
                allowResize: true,
                sortable: false
            }, {
                property: 'product.productNumber',
                label: this.$tc('sw-product.list.columnProductNumber'),
                allowResize: true,
                sortable: false
            }, {
                property: 'position',
                label: this.$tc('sw-product.crossselling.inputCrossSellingPosition'),
                allowResize: true,
                sortable: false
            }];
        }
    },

    methods: {
        onToggleProduct(productId) {
            if (productId === null) {
                return;
            }

            this.isLoadingData = true;
            const matchedAssignedProduct = this.assignedProducts.find((assignedProduct) => {
                return assignedProduct.productId === productId;
            });

            if (matchedAssignedProduct) {
                this.removeItem(matchedAssignedProduct);
                this.isLoadingData = false;
            } else {
                const newProduct = this.assignmentRepository.create();
                newProduct.crossSellingId = this.crossSellingId;
                newProduct.productId = productId;
                newProduct.position = this.assignedProducts.length + 1;
                this.assignedProducts.add(newProduct);

                this.productRepository.get(productId, Context.api).then((product) => {
                    newProduct.product = product;
                    this.isLoadingData = false;
                });
            }
        },

        removeItem(item) {
            const oldPosition = item.position;

            this.assignedProducts.remove(item.id);
            this.assignedProducts.forEach((assignedProduct) => {
                if (assignedProduct.position <= oldPosition) {
                    return;
                }

                assignedProduct.position -= 1;
            });
        },

        isSelected(item) {
            return this.assignedProducts.some(p => p.productId === item.id);
        }
    }
});
