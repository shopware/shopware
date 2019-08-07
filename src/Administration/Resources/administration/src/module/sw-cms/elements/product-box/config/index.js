import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-cms-el-config-product-box.html.twig';
import './sw-cms-el-config-product-box.scss';

Component.register('sw-cms-el-config-product-box', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        productStore() {
            return State.getStore('product');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-box');
        },

        onProductChange(productId) {
            if (!productId) {
                this.element.config.product.value = null;
                this.$set(this.element.data, 'productId', null);
                this.$set(this.element.data, 'product', null);
            } else {
                const product = this.productStore.getById(productId);

                this.element.config.product.value = productId;
                this.$set(this.element.data, 'productId', productId);
                this.$set(this.element.data, 'product', product);
            }

            this.$emit('element-update', this.element);
        }
    }
});
