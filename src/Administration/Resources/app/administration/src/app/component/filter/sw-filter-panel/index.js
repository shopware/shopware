import template from './sw-filter-panel.html.twig';
import './sw-filter-panel.scss';

const { Component } = Shopware;

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

        // TODO: handle default filters
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
            const filters = [];

            Object.values(this.activeFilters).forEach(activeFilter => {
                filters.push(...activeFilter);
            });

            return filters;
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
