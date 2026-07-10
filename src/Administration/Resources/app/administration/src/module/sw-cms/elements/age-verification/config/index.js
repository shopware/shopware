import template from './sw-cms-el-config-age-verification.html.twig';
import './sw-cms-el-config-age-verification.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    emits: ['element-update'],

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

        onFieldChange(field, value) {
            if (this.element.config[field].value === value) {
                return;
            }

            this.element.config[field].value = value;
            this.$emit('element-update', this.element);
        },
    },
};
