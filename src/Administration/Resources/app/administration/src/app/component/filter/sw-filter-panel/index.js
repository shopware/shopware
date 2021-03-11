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
        },

        isFilterActive() {
            return this.activeFiltersNumber > 0;
        },

        activeFiltersNumber() {
            return Object.keys(this.activeFilters).length;
        }
    },

    watch: {
        criteria: {
            handler() {
                this.$emit('criteria-changed', this.criteria);
            },
            deep: true
        },
        activeFiltersNumber() {
            this.$emit('active-filter-number-update', this.activeFiltersNumber);
        }
    },

    methods: {
        updateFilter(name, filter) {
            this.$set(this.activeFilters, name, filter);
        },

        resetFilter(name) {
            this.$delete(this.activeFilters, name);
        },

        resetAll() {
            this.activeFilters = {};
        },

        showFilter(filter, type) {
            return filter.type === type && this.defaults.includes(filter.name);
        },

        getBreadcrumb(item) {
            if (item.breadcrumb) {
                return item.breadcrumb.join(' / ');
            }
            return item.translated.name || item.name;
        },

        getLabelName(item) {
            if (item.breadcrumb && item.breadcrumb.length > 1) {
                return `.. / ${item.translated.name || item.name} `;
            }

            return item.translated.name || item.name;
        }
    }
});
