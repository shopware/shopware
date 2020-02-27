import template from './sw-product-cross-selling-assignment.html.twig';
import './sw-product-cross-selling-assignment.scss';

const { mapGetters, mapState } = Shopware.Component.getComponentHelper();

const { Component, Context } = Shopware;

Component.register('sw-product-cross-selling-assignment', {
    template,

    inject: ['repositoryFactory'],

    props: {
        assignedProducts: {
            type: Array,
            required: true
        },

        resultLimit: {
            type: Number,
            required: false,
            default: 10
        },

        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true
        },

        labelProperty: {
            type: String,
            required: false,
            default: 'name'
        },

        crossSellingId: {
            type: String,
            required: true
        },

        placeholder: {
            type: String,
            required: false,
            default() {
                return this.$tc('global.entity-components.placeholderToManyAssociationCard');
            }
        },

        searchableFields: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            selectedIds: [],
            resultCollection: null,
            positionColumnKey: 0,
            totalAssigned: 0,
            loadingGridState: this.isLoading,
            isLoadingData: false,
            assignmentGridKey: 0,
            total: 0
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        crossSellingAssigmentRepository() {
            return this.repositoryFactory.create('product_cross_selling_assigned_products');
        },

        context() {
            return this.assignedProducts.context;
        },

        languageId() {
            return this.context.languageId;
        },

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

        currentAssignedProducts: {
            get() {
                return this.assignedProducts;
            },

            set(collection) {
                Shopware.State.commit('swProductDetail/setAssignedProductsFromCrossSelling', {
                    id: this.crossSellingId,
                    collection: collection
                });
            }
        },

        focusEl() {
            return this.$refs.searchInput;
        },

        originalFilters() {
            return this.criteria.filters;
        },

        assignedProductColumns() {
            return [{
                property: 'product.name',
                label: this.$tc('sw-product.list.columnName'),
                primary: true,
                allowResize: true,
                sortable: false
            }, {
                property: 'position',
                label: this.$tc('sw-product.crossselling.inputCrossSellingPosition'),
                allowResize: true,
                sortable: false
            }, {
                property: 'product.productNumber',
                label: this.$tc('sw-product.list.columnProductNumber'),
                allowResize: true,
                sortable: false
            }];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.total = this.assignedProducts.length;
        },

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
