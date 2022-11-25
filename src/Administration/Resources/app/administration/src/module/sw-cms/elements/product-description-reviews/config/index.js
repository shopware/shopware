import Criteria from 'src/core/data/criteria.data';
import template from './sw-cms-el-config-product-description-reviews.html.twig';
import './sw-cms-el-config-product-description-reviews.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @package content
 */
export default {
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
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        selectedProductCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('properties');

            return criteria;
        },

        isProductPage() {
            return this.cmsPageState?.currentPage?.type === 'product_detail';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-description-reviews');
        },

        onProductChange(productId) {
            if (!productId) {
                this.element.config.product.value = null;
                this.$set(this.element.data, 'productId', null);
                this.$set(this.element.data, 'product', null);
            } else {
                this.productRepository.get(
                    productId,
                    this.productSelectContext,
                    this.selectedProductCriteria,
                ).then((product) => {
                    this.element.config.product.value = productId;
                    this.$set(this.element.data, 'productId', productId);
                    this.$set(this.element.data, 'product', product);
                });
            }

            this.$emit('element-update', this.element);
        },
    },
};
