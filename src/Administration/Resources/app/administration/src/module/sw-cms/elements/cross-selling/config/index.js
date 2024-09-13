import template from './sw-cms-el-config-cross-selling.html.twig';
import './sw-cms-el-config-cross-selling.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    emits: ['element-update'],

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

        async onProductChange(productId) {
            if (productId) {
                await this.fetchProduct(productId);
            } else {
                this.deleteProduct();
            }

            this.$emit('element-update', this.element);
        },

        async fetchProduct(productId) {
            const product = await this.productRepository.get(
                productId,
                this.productSelectContext,
                this.selectedProductCriteria,
            );
            this.element.config.product.value = productId;

            if (this.isCompatEnabled('INSTANCE_SET')) {
                this.$set(this.element.data, 'product', product);
            } else {
                this.element.data.product = product;
            }
        },

        deleteProduct() {
            this.element.config.product.value = null;

            if (this.isCompatEnabled('INSTANCE_SET')) {
                this.$set(this.element.data, 'product', null);
            } else {
                this.element.data.product = null;
            }
        },
    },
};
