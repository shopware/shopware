import template from './sw-sales-channel-products-assignment-modal.html.twig';

const { Component } = Shopware;
const { uniqBy } = Shopware.Utils.array;

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
            singleProducts: [],
            groupProducts: []
        };
    },

    computed: {
        products() {
            return uniqBy([...this.singleProducts, ...this.groupProducts], 'id');
        }
    },

    methods: {
        onChangeSelection(products, type) {
            this[type] = Object.values(products);
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('products-add', this.products);
        }
    }
});
