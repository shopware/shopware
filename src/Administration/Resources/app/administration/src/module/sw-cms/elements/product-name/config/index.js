const { Component } = Shopware;

Component.extend('sw-cms-el-config-product-name', 'sw-cms-el-config-text', {
    methods: {
        createdComponent() {
            this.initElementConfig('product-name');
        }
    }
});
