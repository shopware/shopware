import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-config-product-slider.html.twig';
import './sw-cms-el-config-product-slider.scss';

Component.register('sw-cms-el-config-product-slider', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        products() {
            if (this.element.data.products && this.element.data.products.length > 0) {
                return this.element.data.products;
            }

            return null;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-slider');
        },

        onProductsChange(products) {
            this.element.config.products.value = [];

            products.forEach((product) => {
                this.element.config.products.value.push(product.id);
            });

            this.$set(this.element.data, 'products', products);
        }
    }
});
