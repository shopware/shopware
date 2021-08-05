import template from './sw-cms-el-category-navigation.html.twig';
import './sw-cms-el-category-navigation.scss';

Shopware.Component.register('sw-cms-el-category-navigation', {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
        Shopware.Mixin.getByName('placeholder'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-navigation');
        },
    },
});
