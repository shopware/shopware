import template from './sw-boolean-filter.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-boolean-filter', {
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
            value: null
        };
    },

    methods: {
        changeValue(newValue) {
            if (!newValue) {
                this.resetFilter();
                return;
            }

            this.value = newValue;

            const filterCriteria = [Criteria.equals(this.filter.property, this.value === 'true')];

            this.$emit('filter-update', this.filter.name, filterCriteria);
        },

        resetFilter() {
            this.value = null;
            this.$emit('filter-reset', this.filter.name);
        }
    }
});
