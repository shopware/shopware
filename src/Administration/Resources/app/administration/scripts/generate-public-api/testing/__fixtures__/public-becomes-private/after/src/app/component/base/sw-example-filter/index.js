/**
 * @sw-package framework
 */

import template from './sw-example-filter.html.twig';

/**
 * @private
 */
export default {
    template,

    emits: ['sw-example-filter-term-change'],

    props: {
        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        delay: {
            type: Number,
            required: false,
            default: 500,
        },
    },

    data() {
        return {
            term: '',
        };
    },

    methods: {
        onSearchTermChange() {
            this.$emit('sw-example-filter-term-change', this.term);
        },
    },
};
