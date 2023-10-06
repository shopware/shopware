import template from './sw-base-filter.html.twig';
import './sw-base-filter.scss';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-base-filter', {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
        showResetButton: {
            type: Boolean,
            required: true,
        },
        active: {
            type: Boolean,
            required: true,
        },
    },

    watch: {
        active(value) {
            if (!value) {
                this.resetFilter();
            }
        },
    },

    methods: {
        resetFilter() {
            this.$emit('filter-reset');
        },
    },
});
