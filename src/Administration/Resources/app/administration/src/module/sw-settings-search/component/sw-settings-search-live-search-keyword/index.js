/**
 * @package buyers-experience
 */
import template from './sw-settings-search-live-search-keyword.html.twig';
import './sw-settings-search-live-search-keyword.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        text: {
            type: String,
            required: true,
            default: null,
        },

        searchTerm: {
            type: String,
            required: true,
            default: null,
        },

        highlightClass: {
            type: String,
            required: false,
            default: 'sw-settings-search-live-search-keyword__highlight',
        },
    },

    computed: {
        textIsHighlighted() {
            return this.text.includes(this.highlightClass);
        },

        parsedSearch() {
            // escaped regexep
            const term = this.searchTerm.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return `(${term.replace(/ +/g, '|')})`;
        },

        parsedMsg() {
            return this.text.split(new RegExp(this.parsedSearch, 'gi'));
        },
    },

    methods: {
        getClass(index) {
            return index ? this.highlightClass : {};
        },
    },
};
