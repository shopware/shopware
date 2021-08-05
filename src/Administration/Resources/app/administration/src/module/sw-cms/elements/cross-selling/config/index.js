import template from './sw-cms-el-config-cross-selling.html.twig';
import './sw-cms-el-config-cross-selling.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-cms-el-config-cross-selling', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productSelectContext() {
            return {
                ...Shopware.Context.api,
                inheritance: true,
            };
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('options.group');

            return criteria;
        },

        selectedProductCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('crossSellings.assignedProducts.product');

            return criteria;
        },

        isProductPageType() {
            return this.cmsPageState?.currentPage?.type === 'product_detail';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('cross-selling');
        },

        onProductChange(productId) {
            if (!productId) {
                this.element.config.product.value = null;
                this.$set(this.element.data, 'product', null);
            } else {
                this.productRepository.get(productId, this.productSelectContext, this.selectedProductCriteria)
                    .then((product) => {
                        this.element.config.product.value = productId;
                        this.$set(this.element.data, 'product', product);
                    });
            }

            this.$emit('element-update', this.element);
        },
    },
});
