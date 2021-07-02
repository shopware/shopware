import template from './sw-sales-channel-products-assignment-modal.html.twig';
import './sw-sales-channel-products-assignment-modal.scss';

const { Component } = Shopware;
const { uniqBy } = Shopware.Utils.array;

Component.register('sw-sales-channel-products-assignment-modal', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        isAssignProductLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            singleProducts: [],
            categoryProducts: [],
            groupProducts: [],
            isProductLoading: false,
        };
    },

    computed: {
        productCount() {
            return this.products.length;
        },

        products() {
            return uniqBy([...this.singleProducts, ...this.categoryProducts, ...this.groupProducts], 'id');
        },
    },

    methods: {
        onChangeSelection(products, type) {
            this[type] = products;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('products-add', this.products);
        },

        setProductLoading(isProductLoading) {
            this.isProductLoading = isProductLoading;
        },
    },
});
