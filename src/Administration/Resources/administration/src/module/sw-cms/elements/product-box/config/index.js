import { Component } from 'src/core/shopware';
import template from './sw-cms-el-config-product-box.html.twig';
import './sw-cms-el-config-product-box.scss';

Component.register('sw-cms-el-config-product-box', {
    template,

    data() {
        return {};
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {}
    }
});
