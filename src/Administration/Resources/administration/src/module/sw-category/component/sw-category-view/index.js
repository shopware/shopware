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
            productsLoaded: false
        };
    },

    watch: {
        '$route.params.id'() {
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
            console.log('createdView', this.$route.params.id, this.category.id);
        },

        getList() {
            console.log('getListView', this.$route.params.id, this.category.id);
            const params = this.getListingParams();
            this.productsLoaded = false;
            this.products = [];

            this.categoryProductStore.getList(params).then(response => {
                this.total = response.total;
                this.items = response.items;
                this.products = response.items;
                this.productsLoaded = true;
                console.log(response.total);
                console.log(response.items);
            });
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

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onAddProduct(productId) {
            // remove product if its already added
            if (this.products.find(product => product.id === productId)) {
                this.onRemoveProduct(productId);
                return false;
            }

            const product = this.productStore.getById(productId);
            // do a loop if multiselect is possible in the sw-select without fuckups
            if (!this.categoryProductStore.store[product.id]) {
                this.categoryProductStore.create(product.id, product, true);
            }
            // In case the entity was already created but was deleted before
            this.categoryProductStore.store[product.id].isDeleted = false;
            // push the product into the local variable
            this.products.push(product);
            return true;
        },

        onRemoveProduct(productId) {
            // remove product out of the associationStore
            const productStoreEntry = this.categoryProductStore.getById(productId);
            productStoreEntry.delete();
            // remove product out of the local products variable
            const match = this.products.find(assignedProduct => assignedProduct.id === productId);
            const index = this.products.indexOf(match);
            this.products.splice(index, 1);
        }
    }
});
