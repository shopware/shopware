import template from './sw-many-to-many-assignment-card.html.twig';
import './sw-many-to-many-assignment-card.scss';

const { Component } = Shopware;
const { debounce, get } = Shopware.Utils;
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-many-to-many-assignment-card
 *     title="your card title"
 *     :entityCollection="entity.association"
 *     :localMode="entity.isNew()"
 *     :searchableFields="['entity.fieldName', 'entity.otherFieldName']">
 *
 * <sw-many-to-many-assignment-card>
 */
Component.register('sw-many-to-many-assignment-card', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    model: {
        property: 'entityCollection',
        event: 'change',
    },

    props: {
        columns: {
            type: Array,
            required: true,
        },

        entityCollection: {
            type: Array,
            required: true,
        },

        localMode: {
            type: Boolean,
            required: true,
        },

        resultLimit: {
            type: Number,
            required: false,
            default: 25,
        },

        criteria: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, this.resultLimit);
            },
        },

        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true,
        },

        labelProperty: {
            type: String,
            required: false,
            default: 'name',
        },

        selectLabel: {
            type: String,
            required: false,
            default: '',
        },

        placeholder: {
            type: String,
            required: false,
            default() {
                return this.$tc('global.entity-components.placeholderToManyAssociationCard');
            },
        },

        searchableFields: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            gridCriteria: null,
            searchCriteria: null,
            isLoadingResults: false,
            isLoadingGrid: false,
            selectedIds: [],
            resultCollection: null,
            gridData: [],
            searchTerm: '',
            totalAssigned: 0,
            loadingGridState: false,
        };
    },

    computed: {
        context() {
            return this.entityCollection.context;
        },

        languageId() {
            return this.context.languageId;
        },

        assignmentRepository() {
            return this.repositoryFactory.create(
                this.entityCollection.entity,
                this.entityCollection.source,
            );
        },

        searchRepository() {
            return this.repositoryFactory.create(
                this.entityCollection.entity,
            );
        },

        page: {
            get() { return this.gridCriteria.page; },
            set(page) { this.gridCriteria.page = page; },
        },

        limit: {
            get() { return this.gridCriteria.limit; },
            set(limit) { this.gridCriteria.page = limit; },
        },

        total() {
            return this.localMode ? this.entityCollection.length : this.gridData.total || 0;
        },

        focusEl() {
            return this.$refs.searchInput;
        },

        originalFilters() {
            return this.criteria.filters;
        },
    },

    watch: {
        criteria: {
            immediate: true,
            handler() {
                this.gridCriteria = Criteria.fromCriteria(this.criteria);
                this.searchCriteria = Criteria.fromCriteria(this.criteria);

                if (!this.localMode) {
                    this.paginateGrid();
                }
            },
        },

        entityCollection() {
            this.selectedIds = this.entityCollection.getIds();

            if (!this.localMode) {
                this.paginateGrid();
                return;
            }

            this.gridData = this.entityCollection;
        },

        languageId() {
            if (!this.localMode) {
                this.paginateGrid();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initData();
        },

        initData() {
            this.page = 1;
            if (!this.localMode) {
                this.selectedIds = this.entityCollection.getIds();
                return;
            }
            this.gridData = this.entityCollection;
        },

        onSearchTermChange(input) {
            this.searchTerm = input.target.value || null;

            this.debouncedSearch();
        },

        debouncedSearch: debounce(function debouncedSearch() {
            this.resetSearchCriteria();
            this.searchCriteria.term = this.searchTerm || null;

            this.addContainsFilter(this.searchCriteria);

            this.searchItems().then((searchResult) => {
                this.resultCollection = searchResult;
            });
        }, 500),

        onSelectExpanded() {
            this.resetSearchCriteria();
            this.focusEl.select();

            this.searchItems().then((searchResult) => {
                this.resultCollection = searchResult;
            });
        },

        paginateResult() {
            if (this.resultCollection.length >= this.resultCollection.total) {
                return;
            }

            this.searchCriteria.page += 1;

            this.searchItems().then((searchResult) => {
                this.resultCollection.push(...searchResult);
            });
        },

        searchItems() {
            return this.searchRepository.search(this.searchCriteria, this.context).then((result) => {
                if (!this.localMode) {
                    const criteria = new Criteria(1, this.searchCriteria.limit);
                    criteria.setIds(result.getIds());

                    this.assignmentRepository.searchIds(criteria, this.context).then(({ data }) => {
                        data.forEach((id) => {
                            if (!this.isSelected({ id })) {
                                this.selectedIds.push(id);
                            }
                        });
                    });
                }

                return result;
            });
        },

        onItemSelect(item) {
            if (this.isSelected(item)) {
                this.removeItem(item);
                return;
            }

            if (this.localMode) {
                const newCollection = EntityCollection.fromCollection(this.entityCollection);
                newCollection.push(item);

                this.selectedIds = newCollection.getIds();
                this.gridData = newCollection;

                this.$emit('change', newCollection);
                return;
            }

            this.assignmentRepository.assign(item.id, this.context).then(() => {
                this.selectedIds.push(item.id);
            });
        },

        removeItem(item) {
            if (this.localMode) {
                const newCollection = this.entityCollection.filter((selected) => {
                    return selected.id !== item.id;
                });

                this.selectedIds = newCollection.getIds();
                this.gridData = newCollection;

                this.$emit('change', newCollection);
                return Promise.resolve();
            }

            return this.assignmentRepository.delete(item.id, this.context).then(() => {
                this.selectedIds = this.selectedIds.filter((selectedId) => {
                    return selectedId !== item.id;
                });
            });
        },

        isSelected(item) {
            return this.selectedIds.some((selectedId) => {
                return item.id === selectedId;
            });
        },

        resetActiveItem() {
            this.$refs.swSelectResultList.setActiveItemIndex(0);
        },

        onSelectCollapsed() {
            this.resultCollection = null;
            this.focusEl.blur();

            if (!this.localMode) {
                this.paginateGrid();
            }
        },

        resetSearchCriteria() {
            this.searchCriteria.page = 1;
            this.searchCriteria.term = this.searchTerm || null;
            this.searchCriteria.limit = this.resultLimit;

            this.addContainsFilter(this.searchCriteria);
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },

        paginateGrid({ page, limit } = this.gridCriteria) {
            this.gridCriteria.page = page;
            this.gridCriteria.limit = limit;
            this.setGridFilter();

            this.isLoadingGrid = true;
            this.assignmentRepository.search(this.gridCriteria, this.context).then((assignments) => {
                this.gridData = assignments;
                this.isLoadingGrid = false;
                this.$emit('paginate', this.gridData);
            });
        },

        setGridFilter() {
            this.gridCriteria.term = this.searchTerm || null;
            this.addContainsFilter(this.gridCriteria);
        },

        addContainsFilter(criteria) {
            if (criteria.term === null) {
                criteria.filters = [...this.originalFilters];
                return;
            }

            if (this.searchableFields.length > 0) {
                const containsFilter = this.searchableFields.map((field) => {
                    return Criteria.contains(field, criteria.term);
                });

                criteria.filters = [
                    ...this.criteria.filters,
                    Criteria.multi(
                        'OR',
                        containsFilter,
                    ),
                ];
                criteria.term = null;
            }
        },

        removeFromGrid(item) {
            this.removeItem(item).then(() => {
                if (!this.localMode) {
                    this.paginateGrid();
                }
            });
        },
    },
});
