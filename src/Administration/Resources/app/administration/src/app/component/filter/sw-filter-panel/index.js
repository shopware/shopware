import template from './sw-filter-panel.html.twig';
import './sw-filter-panel.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-filter-panel', {
    template,

    props: {
        entity: {
            type: String,
            required: true
        },

        filters: {
            type: Array,
            required: true
        },

        defaults: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            activeFilters: {}
        };
    },

    computed: {
        criteria() {
            const criteria = new Criteria();

            Object.values(this.activeFilters).forEach(activeFilter => {
                criteria.addFilter(...activeFilter);
            });

            return criteria;
        }
    },

    watch: {
        criteria: {
            handler() {
                this.$emit('criteria-changed', this.criteria);
            },
            deep: true
        }
    },

    methods: {
        updateFilter(name, filter) {
            this.$set(this.activeFilters, name, filter);
        },

        resetFilter(name) {
            this.$delete(this.activeFilters, name);
        }
    }
});
