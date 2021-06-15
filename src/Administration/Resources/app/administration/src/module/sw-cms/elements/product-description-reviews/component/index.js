import template from './sw-cms-el-product-description-reviews.html.twig';
import './sw-cms-el-product-description-reviews.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-product-description-reviews', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder'),
    ],

    computed: {
        product() {
            if (this.currentDemoEntity) {
                return this.currentDemoEntity;
            }

            if (!this.element.data || !this.element.data.product) {
                return {
                    name: 'Product information',
                    description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod
                                  tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.
                                  At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren,
                                  no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet,
                                  consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et
                                  dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo
                                  dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem
                                  ipsum dolor sit amet.`,
                };
            }

            return this.element.data.product;
        },

        pageType() {
            return this.cmsPageState?.currentPage?.type;
        },

        isProductPageType() {
            return this.pageType === 'product_detail';
        },

        alignStyle() {
            if (!this.element.config.alignment || !this.element.config.alignment.value) {
                return null;
            }

            return `align-content: ${this.element.config.alignment.value};`;
        },

        currentDemoEntity() {
            if (this.cmsPageState.currentMappingEntity === 'product') {
                return this.cmsPageState.currentDemoEntity;
            }

            return null;
        },
    },

    watch: {
        pageType(newPageType) {
            this.$set(this.element, 'locked', (newPageType === 'product_detail'));
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-description-reviews');
            this.initElementData('product-description-reviews');
            this.$set(this.element, 'locked', this.isProductPageType);
        },
    },
});
