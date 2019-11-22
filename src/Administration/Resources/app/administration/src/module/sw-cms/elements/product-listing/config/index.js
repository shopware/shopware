import template from './sw-cms-el-config-product-listing.html.twig';
import './sw-cms-el-config-product-listing.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-product-listing', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-listing');
        }
    }
});
