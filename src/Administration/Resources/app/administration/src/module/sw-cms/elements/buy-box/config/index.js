import template from './sw-cms-el-config-buy-box.html.twig';
import './sw-cms-el-config-buy-box.scss';

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
            criteria.addAssociation('deliveryTime');

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
            this.initElementConfig('buy-box');
        },

        onProductChange(productId) {
            if (!productId) {
                this.element.config.product.value = null;

                if (this.isCompatEnabled('INSTANCE_SET')) {
                    this.$set(this.element.data, 'productId', null);
                    this.$set(this.element.data, 'product', null);
                } else {
                    this.element.data.productId = null;
                    this.element.data.product = null;
                }
            } else {
                this.productRepository.get(productId, this.productSelectContext, this.selectedProductCriteria)
                    .then((product) => {
                        this.element.config.product.value = productId;

                        if (this.isCompatEnabled('INSTANCE_SET')) {
                            this.$set(this.element.data, 'productId', productId);
                            this.$set(this.element.data, 'product', product);
                        } else {
                            this.element.data.productId = productId;
                            this.element.data.product = product;
                        }
                    });
            }

            this.$emit('element-update', this.element);
        },
    },
};
