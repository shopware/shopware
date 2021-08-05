import template from './sw-boolean-filter.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-boolean-filter', {
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

    computed: {
        value() {
            return this.filter.value;
        },
    },

    methods: {
        changeValue(newValue) {
            if (!newValue) {
                this.resetFilter();
                return;
            }

            const filterCriteria = [Criteria.equals(this.filter.property, newValue === 'true')];

            this.$emit('filter-update', this.filter.name, filterCriteria, newValue);
        },

        resetFilter() {
            this.$emit('filter-reset', this.filter.name);
        },
    },
});
