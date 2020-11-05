const { Component } = Shopware;

Component.extend('sw-cms-el-product-name', 'sw-cms-el-text', {
    methods: {
        createdComponent() {
            this.initElementConfig('product-name');
        }
    }
});
