import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-product-listing.html.twig';
import './sw-cms-el-product-listing.scss';

Component.register('sw-cms-el-product-listing', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-box');
        }
    }
});
