import template from './sw-entity-advanced-selection-modal.html.twig';
import './sw-entity-advanced-selection-modal.scss';

const { Component, Mixin } = Shopware;
const { debounce } = Shopware.Utils;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @description This component should not be used directly.
 * Instead, create a wrapper component which defines all the props and can be passed to
 * `sw-entity-...-select` components as `advanced-selection-component="your-component-name"`.
 * Also have a look for already existing wrapper components for your entity.
 * @status prototype
 */
Component.register('sw-entity-advanced-selection-modal', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'filterFactory',
        'filterService',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    props: {
        entityName: {
            type: String,
            required: true,
        },
        // Translated entity name to display in the modal title.
        entityDisplayText: {
            type: String,
            required: true,
        },
        // A unique identifier for this kind of advanced selection.
        // The same uniquely configured modal for a single entity can have the same key.
        // It is passed to the sw-filter-panel and sw-entity-listing to retrieve user configured data
        // like visible columns, column order and the last filters that were applied.
        // TODO - NEXT-20791 : filters should not be stored somewhere
        storeKey: {
            type: String,
            required: true,
        },
        // An array of column information. This is passed to the 'columns' property of the sw-entity-listing.
        entityColumns: {
            type: Array,
            required: true,
        },
        // A key-value object containing all the possible filter definitions under a unique identifier.
        // This is passed to the `filters` property of the sw-filter-panel after
        // a call to filterFactory.create(...)
        entityFilters: {
            type: Object,
            required: true,
        },
        // Path to an image that is used as an Icon for the empty state.
        // This depends on what entity is used for the modal and where it is found in the administration.
        emptyImagePath: {
            type: String,
            required: true,
        },
        // Additional associations which can't be inferred from the entityColumns or entityFilters.
        // This is most likely needed if the column slots are used for custom rendering and usage of associations.
        entityAssociations: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
        isSingleSelect: {
            type: Boolean,
            required: false,
            default: false,
        },
        // Callback functions which receives one item of the entity and returns true or false,
        // depending on if the corresponding grid row should be selectable.
        // This is passed to the 'is-record-selectable-callback' property of the sw-entity-advanced-selection-modal-grid.
        isRecordSelectableCallback: {
            type: Function,
            required: false,
            // by default no callback function should be provided to the sw-entity-advanced-selection-modal-grid
            default: undefined,
        },
        // Additional criteria filters that should always apply.
        criteriaFilters: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
        criteriaAggregations: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
        // Custom context which is used for the search requests. If none is specified the default API context is used.
        entityContext: {
            type: Object,
            required: false,
            default() {
                return Shopware.Context.api;
            },
        },
        // Optional search term which should be applied to the search field.
        initialSearchTerm: {
            type: String,
            required: false,
            default() {
                return '';
            },
        },
        // An array containing the already selected items.
        initialSelection: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
        disablePreviews: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isLoading: true, // must be true on component creation
            entities: [],
            aggregations: [],
            currentSelection: {},
            filterCriteria: [],
            disableRouteParams: true,
            filterWindowOpen: false,
        };
    },

    computed: {
        modalTitle() {
            return this.$tc('global.sw-entity-advanced-selection-modal.title', 1, {
                entity: this.entityDisplayText,
            });
        },

        entityRepository() {
            return this.repositoryFactory.create(this.entityName);
        },

        entityDefinition() {
            return Shopware.EntityDefinition.get(this.entityName);
        },

        assignmentProperties() {
            const properties = [];

            Object.entries(this.entityDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                    properties.push(propertyName);
                }
            });

            return properties;
        },

        allEntityAssociations() {
            // add all custom associations which might be needed in the template slots
            const allAssociations = new Set(this.entityAssociations);

            // get associations from property usage in entityColumns
            this.entityColumns.forEach((column) => {
                if (column.property && column.property.includes('.')) {
                    const propertyDotIndex = column.property.lastIndexOf('.');
                    allAssociations.add(column.property.slice(0, propertyDotIndex));
                }
            });

            // get associations from property usage in entityFilters
            Object.values(this.entityFilters).forEach((filter) => {
                if (filter.property && filter.property.includes('.')) {
                    const propertyDotIndex = filter.property.lastIndexOf('.');
                    allAssociations.add(filter.property.slice(0, propertyDotIndex));
                }
            });

            return allAssociations;
        },

        entityCriteria() {
            // basic pagination + search criteria setup
            const defaultCriteria = new Criteria(this.page, this.limit);
            defaultCriteria.setTerm(this.term);

            if (this.sortBy) {
                this.sortBy.split(',').forEach(sortBy => {
                    const sorting = Criteria.sort(sortBy, this.sortDirection, this.naturalSorting);
                    if (this.assignmentProperties.includes(this.sortBy)) {
                        sorting.field += '.id';
                        sorting.type = 'count';
                    }
                    defaultCriteria.addSorting(sorting);
                });
            }

            // add all associations which are either provided or needed by the columns or filters
            this.allEntityAssociations.forEach((association) => {
                defaultCriteria.addAssociation(association);
            });

            // add custom filters which should always apply
            this.criteriaFilters.forEach(filter => {
                defaultCriteria.addFilter(filter);
            });

            // add selected filters
            this.filterCriteria.forEach(filter => {
                defaultCriteria.addFilter(filter);
            });

            // add aggregations
            this.criteriaAggregations.forEach(aggregation => {
                defaultCriteria.addAggregation(aggregation);
            });

            return defaultCriteria;
        },

        activeFilterNumber() {
            return this.filterCriteria.length;
        },

        defaultFilters() {
            return Object.keys(this.entityFilters);
        },

        listFilters() {
            return this.filterFactory.create(this.entityName, this.entityFilters);
        },

        previewColumns() {
            if (this.disablePreviews) {
                return [];
            }

            return this.entityColumns;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.term = `${this.initialSearchTerm}`;
            this.initialSelection.forEach((selection) => {
                this.currentSelection[selection.id] = selection;
            });

            // TODO - NEXT-20791 : filters should not be stored somewhere
            this.filterService.getStoredCriteria(this.storeKey).then((criteria) => {
                this.filterCriteria.push(...criteria);
                this.isLoading = false;
                return this.getList();
            });
        },

        async getList() {
            if (this.isLoading) {
                // don't fetch if still in loading state
                // (for example on component creation the stored filter criteria must first be fetched)
                return Promise.resolve();
            }
            this.isLoading = true;

            return this.entityRepository.search(this.entityCriteria, this.entityContext).then((items) => {
                this.total = items.total;
                this.entities = items;
                this.aggregations = items.aggregations;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onSelectionChange(selection) {
            this.currentSelection = selection;
        },

        onApply() {
            const items = Object.values(this.currentSelection);

            this.$emit('selection-submit', items);
            this.$emit('modal-close');
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;

            this.debouncedGetList();
        },

        debouncedGetList: debounce(function onGetList() {
            this.getList();
        }, 400),

        clearFilters() {
            this.$refs.filterPanel.resetAll();
        },
    },
});
