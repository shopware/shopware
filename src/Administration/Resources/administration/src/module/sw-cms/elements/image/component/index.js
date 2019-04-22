import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-image.html.twig';
import './sw-cms-el-image.scss';

Component.register('sw-cms-el-image', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        classes() {
            return {
                'is--cover': this.element.config.displayMode.value === 'cover'
            };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image');
        }
    }
});
