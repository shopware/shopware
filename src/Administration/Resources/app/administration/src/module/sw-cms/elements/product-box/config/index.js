import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-cms-el-config-product-box.html.twig';
import './sw-cms-el-config-product-box.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-product-box', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    inject: ['repositoryFactory'],

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productSelectContext() {
            const context = Object.assign({}, Shopware.Context.api);
            context.inheritance = true;

            return context;
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
                const criteria = new Criteria();
                criteria.addAssociation('cover');

                this.productRepository.get(productId, this.productSelectContext, criteria).then((product) => {
                    this.element.config.product.value = productId;
                    this.$set(this.element.data, 'productId', productId);
                    this.$set(this.element.data, 'product', product);
                });
            }

            this.$emit('element-update', this.element);
        }
    }
});
