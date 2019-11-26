import template from './sw-product-cross-selling-form.html.twig';
import './sw-product-cross-selling-form.scss';

const {Component} = Shopware;

Component.register('sw-product-cross-selling-form', {
    inject: ['repositoryFactory'],
    template,

    props: {
        crossSelling: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            sortingTypes: [
                { value: 'priceAsc', label: this.$tc('sw-product.crossselling.priceAscendingSortingType') },
                { value: 'priceDesc', label: this.$tc('sw-product.crossselling.priceDescendingSortingType') },
                { value: 'name', label: this.$tc('sw-product.crossselling.nameSortingType') },
                { value: 'releaseDate', label: this.$tc('sw-product.crossselling.releaseDateSortingType') }
            ],
            showDeleteModal: false
        };
    },

    computed: {
        product() {
            const state = Shopware.State.get('swProductDetail');

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.product;
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        }
    },

    methods: {
        onShowDeleteModal() {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.onCloseDeleteModal();
            this.$nextTick(() => {
                this.product.crossSellings.remove(this.crossSelling.id);
            });
        }
    }
});
