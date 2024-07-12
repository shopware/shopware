import template from './sw-filter-panel.html.twig';
import './sw-filter-panel.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-filter-panel', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    props: {
        filters: {
            type: Array,
            required: true,
        },

        defaults: {
            type: Array,
            required: true,
        },

        storeKey: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            activeFilters: {},
            filterChanged: false,
            storedFilters: null,
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
        },

        listFilters() {
            const savedFilters = { ...this.storedFilters };
            const filters = [];

            this.filters.forEach(el => {
                const filter = { ...el };

                filter.value = savedFilters[filter.name] ? savedFilters[filter.name].value : null;
                filter.filterCriteria = savedFilters[filter.name] ? savedFilters[filter.name].criteria : null;

                filters.push(filter);
            });

            return filters;
        },
    },

    watch: {
        criteria: {
            handler() {
                if (this.filterChanged) {
                    Shopware.Service('filterService').saveFilters(this.storeKey, this.storedFilters).then(response => {
                        this.storedFilters = response;
                        this.$emit('criteria-changed', this.criteria);
                    });
                }
            },
            deep: true,
        },

        '$route'() {
            this.filterChanged = false;
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.Service('filterService').getStoredFilters(this.storeKey).then(filters => {
                this.activeFilters = {};
                this.storedFilters = filters;

                this.listFilters.forEach(filter => {
                    const criteria = filters[filter.name] ? filters[filter.name].criteria : null;
                    if (criteria) {
                        if (this.isCompatEnabled('INSTANCE_SET')) {
                            this.$set(this.activeFilters, filter.name, criteria);
                        } else {
                            this.activeFilters[filter.name] = criteria;
                        }
                    }
                });
            });
        },

        updateFilter(name, filter, value) {
            this.filterChanged = true;
            if (this.isCompatEnabled('INSTANCE_SET')) {
                this.$set(this.activeFilters, name, filter);
            } else {
                this.activeFilters[name] = filter;
            }
            this.storedFilters[name] = { value: value, criteria: filter };
        },

        resetFilter(name) {
            this.filterChanged = true;
            if (this.isCompatEnabled('INSTANCE_DELETE')) {
                this.$delete(this.activeFilters, name);
            } else {
                delete this.activeFilters[name];
            }
            this.storedFilters[name] = { value: null, criteria: null };
        },

        resetAll() {
            this.filterChanged = true;
            this.activeFilters = {};

            Object.values(this.storedFilters).forEach(el => {
                el.value = null;
                el.criteria = null;
            });
        },

        showFilter(filter, type) {
            return filter.type === type && this.defaults.includes(filter.name);
        },

        getBreadcrumb(item) {
            if (item.breadcrumb) {
                return item.breadcrumb.join(' / ');
            }
            return item.translated?.name || item.name;
        },

        getLabelName(item) {
            if (item.breadcrumb && item.breadcrumb.length > 1) {
                return `.. / ${item.translated?.name || item.name} `;
            }

            return item.translated?.name || item.name;
        },
    },
});
