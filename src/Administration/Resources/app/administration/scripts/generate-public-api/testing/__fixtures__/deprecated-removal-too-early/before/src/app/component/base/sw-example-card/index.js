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

        /** @deprecated tag:v6.8.0 - Will be removed, use the `title` prop instead */
        subtitle: {
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

        /** @deprecated tag:v6.8.0 - Will be removed */
        hasSubtitle() {
            return this.subtitle.length > 0;
        },
    },

    methods: {
        onToggle() {
            this.isExpanded = !this.isExpanded;
            this.$emit('toggle', this.isExpanded);
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        getSubtitleClasses() {
            return {
                'is--muted': this.isLoading,
            };
        },
    },
};
