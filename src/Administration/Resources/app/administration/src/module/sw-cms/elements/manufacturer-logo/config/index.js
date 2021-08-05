const { Component } = Shopware;

Component.extend('sw-cms-el-config-manufacturer-logo', 'sw-cms-el-config-image', {
    computed: {
        isProductPage() {
            return this.cmsPageState?.currentPage?.type ?? '' === 'product_detail';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');

            if (this.isProductPage
                && !this.element?.translated?.config?.media
                && !this.element?.data?.media) {
                this.element.config.media.source = 'mapped';
                this.element.config.media.value = 'product.manufacturer.media';
            }
        },
    },
});
