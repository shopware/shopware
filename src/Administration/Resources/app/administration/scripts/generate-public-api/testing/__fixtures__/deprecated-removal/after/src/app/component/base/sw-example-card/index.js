import template from './sw-example-card.html.twig';

/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['toggle'],

    props: {
        title: {
            type: String,
            required: false,
            default: '',
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isExpanded: true,
        };
    },

    computed: {
        hasTitle() {
            return this.title.length > 0;
        },
    },

    methods: {
        onToggle() {
            this.isExpanded = !this.isExpanded;
            this.$emit('toggle', this.isExpanded);
        },
    },
};
