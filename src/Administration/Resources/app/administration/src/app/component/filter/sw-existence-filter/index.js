import template from './sw-existence-filter.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-existence-filter', {
    template,

    props: {
        filter: {
            type: Object,
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

            let filterCriteria = [Criteria.equals(`${this.filter.property}.${this.filter.schema.localField}`, null)];

            if (this.value === 'true') {
                filterCriteria = [Criteria.not('AND', filterCriteria)];
            }

            this.$emit('updateFilter', this.filter.name, filterCriteria);
        },

        resetFilter() {
            this.value = null;
            this.$emit('resetFilter', this.filter.name);
        }
    }
});
