import template from './sw-field-suffix.html.twig';

export default {
    name: 'sw-field-suffix',
    template,

    props: {
        suffix: {
            type: String,
            required: false,
            default: ''
        }
    },

    computed: {
        hasSuffix() {
            return this.suffix.length || !!this.$slots.default;
        }
    }
};
