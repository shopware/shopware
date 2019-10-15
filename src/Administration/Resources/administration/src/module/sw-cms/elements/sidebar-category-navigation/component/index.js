import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-category-navigation.html.twig';
import './sw-cms-el-category-navigation.scss';

Component.register('sw-cms-el-category-navigation', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-navigation');
        }
    }
});
