import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-category-detail-base.html.twig';
import './sw-category-detail-base.scss';

Component.register('sw-category-detail-base', {
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
            deleteButtonDisabled: true,
            cmsPages: [],
            cmsPageId: null,
            showLayoutSelectionModal: false,

            // @todo remove
            cmsPage: null,
            categoryLink: null,
            hasCategoryLink: null
        };
    },

    computed: {
        productStore() {
            return State.getStore('product');
        },

        mediaStore() {
            return State.getStore('media');
        },

        categoryProductStore() {
            return this.category.getAssociation('products');
        },

        cmsPageStore() {
            return State.getStore('cms_page');
        }
    },

    methods: {
        getList() {
            this.isLoadingProducts = true;
            const params = this.getListingParams();

            Promise.all([this.cmsPageStore.getList({}, true), this.categoryProductStore.getList(params)])
                .then(([cmsPagesResponse, productResponse]) => {
                    this.cmsPages = cmsPagesResponse.items;

                    // @todo remove
                    this.cmsPage = cmsPagesResponse.items[0];
                    this.categoryLink = null;
                    this.hasCategoryLink = false;

                    this.products = productResponse.items;
                    this.total = productResponse.total;
                    this.isLoadingProducts = false;
                    this.buildGridArray();
                    return [this.products, this.cmsPages];
                });
        },

        getCmsPage(cmsPageId) {
            return this.cmsPageStore.getByIdAsync(cmsPageId).then(response => {
                this.cmsPage = response;
                return response;
            });
        },

        cmsPageChanged(cmsPageId) {
            this.getCmsPage(cmsPageId);
        },

        onChangeLanguage() {
            this.getList();
        },

        buildGridArray() {
            this.products = this.products.filter(value => value.isLocal === false);
            this.products.splice(0, 0, ...this.getNewItems());
        },

        getNewItems() {
            const newProducts = [];
            this.categoryProductStore.forEach((product) => {
                if (product.isLocal) {
                    newProducts.push(product);
                }
            });
            return newProducts;
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.$emit('sw-category-base-on-set-media', updatedMedia);
            });
        },

        removeMediaItem() {
            this.$emit('sw-category-base-on-remove-media');
        },

        openMediaSidebar() {
            this.$emit('sw-category-base-on-open-sidebar');
        },

        onViewProduct(productId) {
            this.$router.push({ name: 'sw.product.detail', params: { id: productId } });
        },

        onSelectProduct(productId) {
            if (this.products.find(product => product.id === productId)) {
                this.onRemoveDuplicate(productId);
            } else {
                this.addProduct(productId);
            }
        },

        addProduct(productId) {
            this.$refs.productAssignmentSelect.addToAssociations(productId);
            const product = this.productStore.getById(productId);

            if (!this.categoryProductStore.hasId(productId)) {
                const newProduct = this.categoryProductStore.create(productId);
                newProduct.setData(product);
                newProduct.isLocal = true;
                this.products.push(newProduct);
            }

            this.buildGridArray();
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
            this.$refs.productAssignmentSelect.removeFromAssociations(product.id);

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
        },

        onLayoutSelect(selectedLayout) {
            this.cmsPageId = selectedLayout;
            this.getCmsPage(this.cmsPageId).then(response => {
                this.cmsPage = response;
            });
        },

        openInPagebuilder() {
            this.$router.push({ name: 'sw.cms.detail', params: { id: this.cmsPage.id } });
        },

        openLayoutModal() {
            this.showLayoutSelectionModal = true;
        },

        closeLayoutModal() {
            this.showLayoutSelectionModal = false;
        }
    }
});
