import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-cms-el-config-product-slider.html.twig';
import './sw-cms-el-config-product-slider.scss';

const { Component, Mixin } = Shopware;

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
        },

        productMediaFilter() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('cover');

            return criteria;
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
        },

        onChangeDisplayMode(value) {
            if (value === 'cover') {
                this.element.config.verticalAlign.value = '';
            }

            this.$emit('element-update', this.element);
        }
    }
});
