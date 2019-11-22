import template from './sw-cms-el-sidebar-filter.html.twig';
import './sw-cms-el-sidebar-filter.scss';

Shopware.Component.register('sw-cms-el-sidebar-filter', {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('sidebar-filter');
        }
    }
});
