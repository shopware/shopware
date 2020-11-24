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

            if (this.isProductPage) {
                this.element.config.content.source = 'mapped';
                this.element.config.content.value = 'product.name';
            }
        }
    }
});
