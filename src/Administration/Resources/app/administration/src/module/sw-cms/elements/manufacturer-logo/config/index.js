const { Component, Utils } = Shopware;

Component.extend('sw-cms-el-config-manufacturer-logo', 'sw-cms-el-config-image', {
    computed: {
        isProductPage() {
            return Utils.get(this.cmsPageState, 'currentPage.type', '') === 'product_detail';
        }
    },

    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');

            if (this.isProductPage
                && !Utils.get(this.element, 'translated.config.media')
                && !Utils.get(this.element, 'data.media')) {
                this.element.config.media.source = 'mapped';
                this.element.config.media.value = 'product.manufacturer.media';
            }
        }
    }
});
