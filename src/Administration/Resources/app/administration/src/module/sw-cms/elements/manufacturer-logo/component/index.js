const { Component } = Shopware;

Component.extend('sw-cms-el-manufacturer-logo', 'sw-cms-el-image', {
    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');
            this.initElementData('manufacturer-logo');
        }
    }
});
