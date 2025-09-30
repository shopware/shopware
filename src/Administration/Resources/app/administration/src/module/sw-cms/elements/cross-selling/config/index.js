import template from './sw-cms-el-config-cross-selling.html.twig';
import './sw-cms-el-config-cross-selling.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

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

        boxLayoutOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeStandard'),
                },
                {
                    id: 2,
                    value: 'image',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeImage'),
                },
                {
                    id: 3,
                    value: 'minimal',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeMinimal'),
                },
            ];
        },

        displayModeOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    id: 2,
                    value: 'cover',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeCover'),
                },
                {
                    id: 3,
                    value: 'contain',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeContain'),
                },
            ];
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

            this.element.data.product = product;
        },

        deleteProduct() {
            this.element.config.product.value = null;

            this.element.data.product = null;
        },
    },
};
