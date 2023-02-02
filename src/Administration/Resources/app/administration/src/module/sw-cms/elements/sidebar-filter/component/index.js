import template from './sw-cms-el-sidebar-filter.html.twig';
import './sw-cms-el-sidebar-filter.scss';

/**
 * @private since v6.5.0
 */
Shopware.Component.register('sw-cms-el-sidebar-filter', {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
    ],

    computed: {
        componentClasses() {
            return {
                'is--disabled': this.disabled,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('sidebar-filter');
        },
    },
});
