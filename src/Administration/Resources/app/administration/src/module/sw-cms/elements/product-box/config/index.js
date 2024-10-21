import Criteria from 'src/core/data/criteria.data';
import template from './sw-cms-el-config-product-box.html.twig';
import './sw-cms-el-config-product-box.scss';

const { Mixin } = Shopware;

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
            const context = { ...Shopware.Context.api };
            context.inheritance = true;

            return context;
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },
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
                if (this.isCompatEnabled('INSTANCE_SET')) {
                    this.$set(this.element.data, 'productId', null);
                    this.$set(this.element.data, 'product', null);
                } else {
                    this.element.data.productId = null;
                    this.element.data.product = null;
                }
            } else {
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('cover');
                criteria.addAssociation('options.group');

                this.productRepository.get(productId, this.productSelectContext, criteria).then((product) => {
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
