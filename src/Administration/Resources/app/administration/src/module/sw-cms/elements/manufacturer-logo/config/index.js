const { Component } = Shopware;

Component.extend('sw-cms-el-config-manufacturer-logo', 'sw-cms-el-config-image', {
    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');
        }
    }
});
