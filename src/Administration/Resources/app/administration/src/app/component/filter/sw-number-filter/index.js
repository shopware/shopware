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
            required: true,
        },

        active: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            numberValue: {
                from: null,
                to: null,
            },
        };
    },

    watch: {
        'filter.value': {
            handler() {
                if (this.filter.value) {
                    this.numberValue = { ...this.filter.value };
                }
            },
        },
    },

    methods: {
        updateFilter(params) {
            if (!this.numberValue.from && !this.numberValue.to) {
                this.$emit('filter-reset', this.filter.name);
                return;
            }

            const { value } = this.filter;
            if (value && value.from === this.numberValue.from && value.to === this.numberValue.to) {
                return;
            }

            this.$emit('filter-update', this.filter.name, params, this.numberValue);
        },

        resetFilter() {
            this.numberValue = { from: null, to: null };
            this.$emit('filter-reset', this.filter.name, this.numberValue);
        },
    },
});
