import template from './sw-sales-channel-products-assignment-modal.html.twig';

const { Component } = Shopware;

Component.register('sw-sales-channel-products-assignment-modal', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            products: {}
        };
    },

    methods: {
        onChangeSelection(products) {
            this.products = products;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('products-add', this.products);
        }
    }
});
