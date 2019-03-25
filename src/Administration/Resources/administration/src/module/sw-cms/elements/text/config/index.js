import { Component } from 'src/core/shopware';
import template from './sw-cms-el-config-text.html.twig';
import './sw-cms-el-config-text.scss';

Component.register('sw-cms-el-config-text', {
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
