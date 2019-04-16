import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-product-box.html.twig';
import './sw-cms-el-product-box.scss';

Component.register('sw-cms-el-product-box', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        product() {
            return this.element.data.product;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-box');
        }
    }
});
