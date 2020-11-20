import template from './sw-cms-el-buy-box.html.twig';
import './sw-cms-el-buy-box.scss';

const { Component, Mixin, Utils } = Shopware;

Component.register('sw-cms-el-buy-box', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder')
    ],

    computed: {
        product() {
            if (!this.element.data || !this.element.data.product) {
                return {
                    name: 'Lorem Ipsum dolor',
                    productNumber: 'XXXXXX',
                    minPurchase: 1,
                    deliveryTime: {
                        name: '1-3 days'
                    },
                    price: [
                        { gross: 0.00 }
                    ]
                };
            }

            return this.element.data.product;
        },

        pageType() {
            return Utils.get(this.cmsPageState, 'currentPage.type', '');
        },

        isProductPageType() {
            return this.pageType === 'product_detail';
        },

        alignStyle() {
            if (!this.element.config.alignment || !this.element.config.alignment.value) {
                return null;
            }

            return `justify-content: ${this.element.config.alignment.value};`;
        }
    },

    watch: {
        pageType(newPageType) {
            this.$set(this.element, 'locked', newPageType === 'product_detail');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('buy-box');
            this.initElementData('buy-box');
            this.$set(this.element, 'locked', this.isProductPageType);
        }
    }
});
