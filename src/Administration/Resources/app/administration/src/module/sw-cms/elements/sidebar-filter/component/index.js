import template from './sw-cms-el-sidebar-filter.html.twig';
import './sw-cms-el-sidebar-filter.scss';

/**
 * @private
 * @package content
 */
export default {
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
};
