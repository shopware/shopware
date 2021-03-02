import template from './sw-number-filter.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-number-filter', {
    template,

    props: {
        filter: {
            type: Object,
            required: true
        },

        active: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            numberValue: {
                from: null,
                to: null
            }
        };
    },

    methods: {
        updateFilter(...params) {
            this.$emit('filter-update', ...params);
        },

        resetFilter() {
            this.$emit('filter-reset', this.filter.name);
        }
    }
});
