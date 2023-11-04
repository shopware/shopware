import template from './sw-cms-sidebar-nav-element.html.twig';
import './sw-cms-sidebar-nav-element.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        block: {
            type: Object,
            required: true,
        },

        removable: {
            type: Boolean,
            required: false,
            default: false,
        },

        duplicable: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    methods: {
        onBlockDuplicate() {
            this.$emit('block-duplicate', this.block);
        },

        onBlockDelete() {
            this.$emit('block-delete', this.block);
        },
    },
};
