import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-category-navigation.html.twig';
import './sw-cms-el-category-navigation.scss';

Component.register('sw-cms-el-category-navigation', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder')
    ],

    computed: {
        category() {
            return this.element.data.category;
        },

        demoCategoryElement() {
            return {
                name: 'Root Category',
                categories: [
                    {
                        name: 'Sub Category 1'
                    },
                    {
                        name: 'Sub Category 2'
                    },
                    {
                        name: 'Sub Category 3'
                    }
                ]
            };
        }
    }
});
