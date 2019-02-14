import { Component } from 'src/core/shopware';
import template from './sw-cms-el-product-box.html.twig';
import './sw-cms-el-product-box.scss';

Component.register('sw-cms-el-product-box', {
    template,

    model: {
        prop: 'element',
        event: 'element-update'
    },

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

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
