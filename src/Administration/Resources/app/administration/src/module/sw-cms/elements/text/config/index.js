import template from './sw-cms-el-config-text.html.twig';

const { Mixin } = Shopware;

/**
 * @private
 * @package content
 */
export default {
    template,

    data() {
        return {
            content: this.element.config.content.value,
        };
    },

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');
        },

        onBlur(content) {
            this.emitChanges(content);
        },

        onInput(content) {
            this.emitChanges(content);
        },

        emitChanges(content) {
            this.content = content;
        },

        handleUpdateContent() {
            if (this.content !== this.element.config.content.value) {
                this.element.config.content.value = this.content;
                this.$emit('element-update', this.element);
            }
        },
    },
};
