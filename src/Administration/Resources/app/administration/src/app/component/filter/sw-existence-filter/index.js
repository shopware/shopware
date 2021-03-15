import template from './sw-existence-filter.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-existence-filter', {
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

            let filterCriteria = [Criteria.equals(`${this.filter.property}.${this.filter.schema.localField}`, null)];

            if (this.value === 'true') {
                filterCriteria = [Criteria.not('AND', filterCriteria)];
            }

            this.$emit('filter-update', this.filter.name, filterCriteria);
        },

        resetFilter() {
            this.value = null;
            this.$emit('filter-reset', this.filter.name);
        }
    }
});
