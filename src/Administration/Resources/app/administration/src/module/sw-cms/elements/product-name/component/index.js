import './sw-cms-el-product-name.scss';

const { Component, Mixin, Utils } = Shopware;

Component.extend('sw-cms-el-product-name', 'sw-cms-el-text', {
    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        isProductPage() {
            return Utils.get(this.cmsPageState, 'currentPage.type', '') === 'product_detail';
        }
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-name');

            if (this.isProductPage && !Utils.get(this.element, 'translated.config.content')) {
                this.element.config.content.source = 'mapped';
                this.element.config.content.value = 'product.name';
            }
        },

        updateDemoValue() {
            if (this.element.config.content.source === 'mapped') {
                this.demoValue = '<div class="sw-cms-el-product-name__skeleton"></div>';

                if (this.cmsPageState.currentDemoEntity) {
                    this.demoValue = this.getDemoValue(this.element.config.content.value);
                }
            }
        }
    }
});
