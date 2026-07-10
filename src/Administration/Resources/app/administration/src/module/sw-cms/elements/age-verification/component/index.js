import template from './sw-cms-el-age-verification.html.twig';
import './sw-cms-el-age-verification.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('age-verification');
        },
    },
};
