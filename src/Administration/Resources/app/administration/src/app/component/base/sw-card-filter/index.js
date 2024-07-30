/**
 * @package admin
 */

import template from './sw-card-filter.html.twig';
import './sw-card-filter.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-card-filter', {
    template,

    compatConfig: Shopware.compatConfig,

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

        initialSearchTerm: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            term: '',
        };
    },

    computed: {
        hasFilter() {
            return !!this.$slots.filter;
        },

        hasFilterClass() {
            const classCollection = ['sw-card-filter-container'];
            if (this.hasFilter) {
                classCollection.push('hasFilter');
            }

            return classCollection.join(' ');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.term = `${this.initialSearchTerm}`;
        },

        onSearchTermChange() {
            this.$emit('sw-card-filter-term-change', this.term);
        },
    },
});
