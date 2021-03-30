import template from './sw-multi-select-filter.html.twig';
import './sw-multi-select-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-multi-select-filter', {
    template,

    inject: ['repositoryFactory'],

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

    computed: {
        values() {
            return this.filter.value || [];
        }
    },

    methods: {
        changeValue(newValues) {
            if (newValues.length <= 0) {
                this.resetFilter();
                return;
            }

            const filterCriteria = [
                this.filter.schema
                    ? Criteria.equalsAny(
                        `${this.filter.property}.${this.filter.schema.referenceField}`,
                        newValues.map(newValue => newValue[this.filter.schema.referenceField])
                    )
                    : Criteria.equalsAny(this.filter.property, newValues)
            ];

            this.$emit('filter-update', this.filter.name, filterCriteria, newValues);
        },

        resetFilter() {
            this.$emit('filter-reset', this.filter.name);
        }
    }
});
