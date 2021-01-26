import template from './sw-number-filter.html.twig';
import './sw-number-filter.scss';

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
        updateFilter(params) {
            if (!this.numberValue.from && !this.numberValue.to) {
                this.$emit('filter-reset', this.filter.name);
                return;
            }

            this.$emit('filter-update', this.filter.name, params);
        },

        resetFilter() {
            this.numberValue = { from: null, to: null };
            this.$emit('filter-reset', this.filter.name);
        }
    }
});
