import template from './sw-cms-el-form.html.twig';
import './sw-cms-el-form.scss';

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

    computed: {
        selectedForm() {
            if (this.element.config.type.value === 'contact') {
                return 'sw-cms-el-form-template-contact';
            }
            if (this.element.config.type.value === 'newsletter') {
                return 'sw-cms-el-form-template-newsletter';
            }
            return this.element.config.type.value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('form');
        },
    },
};
