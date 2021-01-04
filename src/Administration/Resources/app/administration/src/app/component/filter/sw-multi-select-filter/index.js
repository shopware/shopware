import template from './sw-multi-select-filter.html.twig';
import './sw-multi-select-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-multi-select-filter', {
    template,

    inject: ['repositoryFactory'],

    props: {
        filter: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            values: []
        };
    },

    methods: {
        changeValue(newValues) {
            this.values = newValues;

            if (this.values.length <= 0) {
                this.resetFilter();
                return;
            }

            const filterCriteria = [Criteria.equalsAny(
                `${this.filter.property}.${this.filter.schema.referenceField}`,
                newValues.map(newValue => newValue[this.filter.schema.referenceField])
            )];

            this.$emit('updateFilter', this.filter.name, filterCriteria);
        },

        resetFilter() {
            this.values = [];
            this.$emit('resetFilter', this.filter.name);
        }
    }
});
