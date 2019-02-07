import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-category-view.html.twig';
import './sw-category-view.scss';

Component.register('sw-category-view', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder')
    ],

    props: {
        category: {
            type: Object,
            required: true,
            default: {}
        },
        mediaItem: {
            type: Object,
            required: false,
            default: null
        },
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    data() {
        return {
            products: [],
            entityName: 'product',
            sortBy: 'name',
            sortDirection: 'ASC',
            isLoadingProducts: false,
            deleteButtonDisabled: true
        };
    },

    watch: {
        category() {
            this.getList();
        }
    },

    computed: {
        productStore() {
            return State.getStore('product');
        },

        categoryProductStore() {
            return this.category.getAssociation('products');
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.getList();
        },

        getList() {
            this.isLoadingProducts = true;
            const params = this.getListingParams();
            // this.resetAssignedProducts();

            return this.categoryProductStore.getList(params).then(response => {
                this.total = response.total;
                this.products = response.items;
                this.isLoadingProducts = false;
                // this.buildGridArray();
                return this.products;
            });
        },

        // getList() {
        //     this.isLoading = true;
        //     const params = this.getListingParams();

        //     this.items = [];

        //     return this.store.getList(params).then((response) => {
        //         this.total = response.total;
        //         this.items = response.items;
        //         this.isLoading = false;

        //         return this.items;
        //     });
        // },

        onChangeLanguage() {
            this.getList();
        },

        buildGridArray() {
            this.products = this.products;
            // console.log(this.products.filter(product => product.isLocal === true));
            // this.products = this.products.filter(value => value.isLocal === false);
            // console.log(this.products);
            // console.log(this.getNewItems());
            // this.products.splice(0, 0, ...this.getNewItems());
        },

        // resetAssignedProducts() {
        //     this.isLoadingProducts = false;
        //     this.products = [];
        // },

        getNewItems() {
            const newProducts = [];
            this.categoryProductStore.forEach((product) => {
                if (product.isLocal) {
                    newProducts.push(product);
                }
            });
            return newProducts;
        },

        onUploadAdded({ uploadTag }) {
            this.$emit('sw-category-view-on-upload-media', uploadTag);
        },

        setMediaItem(mediaItem) {
            this.$emit('sw-category-view-on-set-media', mediaItem);
        },

        removeMediaItem(mediaItem) {
            this.$emit('sw-category-view-on-remove-media', mediaItem);
        },

        onViewProduct(productId) {
            const route = this.$router.resolve({ name: 'sw.product.detail', params: { id: productId } });
            window.open(route.href, '_blank');
        },

        onSelectProduct(productId) {
            if (this.products.find(product => product.id === productId)) {
                this.onRemoveDuplicate(productId);
            } else {
                this.addProduct(productId);
            }
        },

        addProduct(productId) {
            const product = this.productStore.getById(productId);

            if (!this.categoryProductStore.hasId(productId)) {
                const newProduct = this.categoryProductStore.create(productId);
                newProduct.setData(product);
                newProduct.isLocal = true;
                this.products.push(newProduct);
            }

            this.buildGridArray();
            return true;
        },

        selectionChanged() {
            const selection = this.$refs.grid.getSelection();
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        onRemoveProducts() {
            const selection = this.$refs.grid.getSelection();
            Object.values(selection).forEach(product => {
                this.onRemoveProduct(product, true);
                this.$refs.grid.selectItem(false, product);
            });
        },

        onRemoveDuplicate(productId) {
            const product = this.products.find(match => match.id === productId);
            this.onRemoveProduct(product);
        },

        onRemoveProduct(product, ignoreUndo = false) {
            if (product.isDeleted && !ignoreUndo) {
                product.isDeleted = false;
                return;
            }

            if (product.isLocal) {
                this.categoryProductStore.removeById(product.id);
                product.delete();
                this.removeSelection(product.id);
            } else {
                product.isDeleted = true;
            }

            this.buildGridArray();
        },

        removeSelection(productId) {
            this.products.forEach((item, index) => {
                if (item.id === productId) {
                    this.products.splice(index, 1);
                }
            });
        }
    }
});
