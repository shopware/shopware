import template from './sw-cms-el-config-product-slider.html.twig';
import './sw-cms-el-config-product-slider.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-cms-el-config-product-slider', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        products() {
            if (this.element.data && this.element.data.products && this.element.data.products.length > 0) {
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

            if (!this.element.data) {
                this.$set(this.element, 'data', {});
            }

            this.$set(this.element.data, 'products', products);
        }
    }
});
