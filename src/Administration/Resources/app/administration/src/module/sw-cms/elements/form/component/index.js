import template from './sw-cms-el-form.html.twig';
import contact from './templates/form-contact/index';
import newsletter from './templates/form-newsletter/index';
import './sw-cms-el-form.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    components: {
        contact,
        newsletter,
    },

    computed: {
        selectedForm() {
            if (this.element.config.type.value === 'contact') {
                return 'sw-cms-el-form-template-contact';
            }
            if (this.element.config.type.value === 'newsletter') {
                return 'sw-cms-el-form-template-newsletter';
            }
            if (this.element.config.type.value === 'revocationRequest') {
                return 'sw-cms-el-form-template-revocation-request';
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
