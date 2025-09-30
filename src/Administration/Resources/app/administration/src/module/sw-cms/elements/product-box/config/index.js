import Criteria from 'src/core/data/criteria.data';
import template from './sw-cms-el-config-product-box.html.twig';
import './sw-cms-el-config-product-box.scss';

const { Mixin } = Shopware;

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
            const context = { ...Shopware.Context.api };
            context.inheritance = true;

            return context;
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        boxLayoutOptions() {
            return [
                {
                    value: 'standard',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeStandard'),
                },
                {
                    value: 'image',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeImage'),
                },
                {
                    value: 'minimal',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeMinimal'),
                },
            ];
        },

        displayModeOptions() {
            return [
                {
                    value: 'standard',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    value: 'cover',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeCover'),
                },
                {
                    value: 'contain',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeContain'),
                },
            ];
        },

        verticalAlignOptions() {
            return [
                {
                    value: 'flex-start',
                    label: this.$tc('sw-cms.elements.general.config.label.verticalAlignTop'),
                },
                {
                    value: 'center',
                    label: this.$tc('sw-cms.elements.general.config.label.verticalAlignCenter'),
                },
                {
                    value: 'flex-end',
                    label: this.$tc('sw-cms.elements.general.config.label.verticalAlignBottom'),
                },
            ];
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
                this.element.data.productId = null;
                this.element.data.product = null;
            } else {
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('cover');
                criteria.addAssociation('options.group');

                this.productRepository.get(productId, this.productSelectContext, criteria).then((product) => {
                    this.element.config.product.value = productId;

                    this.element.data.productId = productId;
                    this.element.data.product = product;
                });
            }

            this.$emit('element-update', this.element);
        },
    },
};
