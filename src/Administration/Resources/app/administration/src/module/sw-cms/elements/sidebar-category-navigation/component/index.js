import template from './sw-cms-el-category-navigation.html.twig';
import './sw-cms-el-category-navigation.scss';
import placeholderMixin from 'shopware:mixins/placeholder';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
        placeholderMixin,
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-navigation');
        },
    },
};
