import cmsElementMixin from 'shopware:mixins/cms-element';
import template from './sw-cms-el-config-html.html.twig';
import './sw-cms-el-config-html.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    emits: ['element-update'],

    mixins: [
        cmsElementMixin,
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('html');
        },

        onBlur(content) {
            this.emitChanges(content);
        },

        onInput(content) {
            this.emitChanges(content);
        },

        emitChanges(content) {
            if (content !== this.element.config.content.value) {
                this.element.config.content.value = content;
                this.$emit('element-update', this.element);
            }
        },
    },
};
