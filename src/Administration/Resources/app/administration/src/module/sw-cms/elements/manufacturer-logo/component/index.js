const { Component, Mixin, Utils } = Shopware;

Component.extend('sw-cms-el-manufacturer-logo', 'sw-cms-el-image', {
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
            this.initElementConfig('manufacturer-logo');
            this.initElementData('manufacturer-logo');

            if (this.isProductPage) {
                this.element.config.media.source = 'mapped';
                this.element.config.media.value = 'product.manufacturer.media';
            }
        }
    }
});
