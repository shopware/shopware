import template from './sw-date-filter.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-date-filter', {
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
            dateValue: {
                from: null,
                to: null
            }
        };
    },

    computed: {
        dateType() {
            if (['time', 'date', 'datetime', 'datetime-local'].includes(this.filter.dateType)) {
                return this.filter.dateType;
            }

            return 'date';
        },

        isDateTimeType() {
            return this.dateType === 'datetime' || this.dateType === 'datetime-local';
        }
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
