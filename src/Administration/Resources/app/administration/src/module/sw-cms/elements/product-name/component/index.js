import './sw-cms-el-product-name.scss';

const { Component, Mixin } = Shopware;

Component.extend('sw-cms-el-product-name', 'sw-cms-el-text', {
    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        isProductPage() {
            return this.cmsPageState?.currentPage?.type ?? '' === 'product_detail';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-name');

            if (this.isProductPage && !this.element?.translated?.config?.content) {
                this.element.config.content.source = 'mapped';
                this.element.config.content.value = 'product.name';
            }
        },

        updateDemoValue() {
            if (this.element.config.content.source === 'mapped') {
                let label = '';
                let className = 'sw-cms-el-product-name__skeleton';

                if (this.element.config.content.value === 'product.name') {
                    className = 'sw-cms-el-product-name__placeholder';
                    label = this.$tc('sw-cms.elements.productName.label');
                }

                this.demoValue = `<h1 class="${className}">${label}</h1>`;

                if (this.cmsPageState.currentDemoEntity) {
                    this.demoValue = this.getDemoValue(this.element.config.content.value);
                }
            }
        },
    },
});
