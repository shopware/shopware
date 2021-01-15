import template from './sw-boolean-filter.html.twig';
import './sw-boolean-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-boolean-filter', {
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
            this.value = newValue;

            const filterCriteria = [Criteria.equals(this.filter.property, newValue)];

            this.$emit('updateFilter', this.filter.name, filterCriteria);
        },

        resetFilter() {
            this.value = null;
            this.$emit('resetFilter', this.filter.name);
        }
    }
});
