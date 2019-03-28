import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-config-text.html.twig';
import './sw-cms-el-config-text.scss';

Component.register('sw-cms-el-config-text', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');
        }
    }
});
