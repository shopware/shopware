import template from './sw-entity-many-to-many-select.html.twig';

const { Component } = Shopware;
const { debounce, get } = Shopware.Utils;
const { deepCopyObject } = Shopware.Utils.object;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('sw-entity-many-to-many-select', {
    template,
    inheritAttrs: false,

    inject: { repositoryFactory: 'repositoryFactory' },

    model: {
        prop: 'entityCollection',
        event: 'change',
    },

    props: {
        labelProperty: {
            type: String,
            required: false,
            default: 'name',
        },
        resultLimit: {
            type: Number,
            required: false,
            default: 25,
        },
        valueLimit: {
            type: Number,
            required: false,
            default: 5,
        },
        // Should be used when creating new entities.
        // Prevents delete or create requests.
        localMode: {
            type: Boolean,
            default: false,
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
        placeholder: {
            type: String,
            required: false,
            default: '',
        },
        entityCollection: {
            type: Array,
            required: true,
        },
        context: {
            type: Object,
            required: false,
            default() {
                return Shopware.Context.api;
            },
        },
    },

    data() {
        return {
            searchTerm: '',
            searchCriteria: null,
            isLoading: false,
            resultCollection: null,
            displayItemsResultCollection: null,
            totalAssigned: 0,
            displayItemLimit: this.valueLimit,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create(this.entityCollection.entity, this.entityCollection.source);
        },

        searchRepository() {
            return this.repositoryFactory.create(this.entityCollection.entity);
        },

        // Used to create the new entityCollection when emitting input,
        // because we dont want to change this.entityCollection directly
        selectedIds: {
            get() {
                return this.entityCollection.getIds();
            },
            set(newIds) {
                this.emitChanges(newIds);
            },
        },

        visibleValues() {
            if (!this.entityCollection || this.entityCollection.length <= 0) {
                return [];
            }

            return this.entityCollection.slice(0, this.displayItemLimit);
        },

        invisibleValueCount() {
            if (!this.entityCollection) {
                return 0;
            }

            if (this.displayItemLimit > this.entityCollection.length) {
                return Math.max(0, this.totalAssigned - this.entityCollection.length);
            }

            return Math.max(0, this.totalAssigned - this.displayItemLimit);
        },
    },

    watch: {
        entityCollection(newVal) {
            // reload data if association was reset but component was not destroyed
            if (newVal.length <= 0 && this.totalAssigned > 0) {
                this.initData();
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
            this.entityCollection.criteria.setLimit(this.valueLimit);
            this.searchCriteria = new Criteria(1, this.resultLimit);

            this.displayAssigned(this.entityCollection);

            if (this.localMode) {
                return Promise.resolve();
            }

            return this.fetchDisplayItems();
        },

        isSelected(item) {
            return this.selectedIds.includes(item.id);
        },

        fetchDisplayItems() {
            this.isLoading = true;
            return this.repository.search(this.entityCollection.criteria, this.entityCollection.context)
                .then((result) => {
                    this.displayAssigned(result);
                    this.isLoading = false;
                    return result;
                });
        },

        displayAssigned(collection) {
            if (collection.total) {
                this.totalAssigned = collection.total;
            } else {
                this.totalAssigned = collection.length;
            }

            collection.forEach((item) => {
                if (!this.entityCollection.has(item.id)) {
                    this.entityCollection.push(item);
                }
            });
        },

        displaySearch(result) {
            if (!this.resultCollection) {
                this.resultCollection = result;
            } else {
                result.forEach(item => {
                    // Prevent duplicate entries
                    if (!this.resultCollection.has(item.id)) {
                        this.resultCollection.push(item);
                    }
                });
            }
        },

        sendSearchRequest() {
            this.isLoading = true;

            if (this.criteria) {
                this.searchCriteria.filters = this.criteria.filters;
            }

            return this.searchRepository.search(this.searchCriteria, Shopware.Context.api)
                .then((searchResult) => {
                    if (searchResult.length <= 0) {
                        this.isLoading = false;
                        return searchResult;
                    }

                    if (this.localMode) {
                        this.displaySearch(searchResult);
                        this.isLoading = false;
                        return Promise.resolve(searchResult);
                    }

                    return this.findAssignedEntities(searchResult.getIds(), searchResult);
                });
        },

        findAssignedEntities(ids, searchResult) {
            const criteria = new Criteria();
            criteria.setIds(ids);

            return this.repository.searchIds(criteria, this.entityCollection.context).then((assigned) => {
                assigned.data.forEach((id) => {
                    if (!this.entityCollection.has(id)) {
                        this.entityCollection.add(searchResult.get(id));
                    }
                });

                this.displaySearch(searchResult);
                this.isLoading = false;

                return searchResult;
            });
        },

        search() {
            if (this.searchCriteria.term === this.searchTerm) {
                return Promise.resolve();
            }

            this.resetSearchCriteria();
            this.resultCollection = null;

            const searchPromise = this.sendSearchRequest();
            searchPromise.then(() => {
                this.resetActiveItem();
            });

            this.$emit('search', searchPromise);

            return searchPromise;
        },

        paginateResult() {
            if (!this.resultCollection
                    || this.resultCollection.total < this.searchCriteria.page * this.searchCriteria.limit) {
                return;
            }

            this.searchCriteria.setPage(this.searchCriteria.page + 1);
            this.sendSearchRequest();
        },

        paginateDisplayList() {
            if (this.totalAssigned < this.entityCollection.criteria.page * this.entityCollection.criteria.limit) {
                return;
            }

            this.entityCollection.criteria.setPage(this.entityCollection.criteria.page + 1);
            this.displayItemLimit = this.entityCollection.criteria.page * this.entityCollection.criteria.limit;
            this.fetchDisplayItems();
        },

        emitChanges(ids) {
            const newEntityCollection = new EntityCollection(
                this.entityCollection.source,
                this.entityCollection.entity,
                this.entityCollection.context,
                this.entityCollection.criteria,
            );

            ids.forEach((id) => {
                let entity = this.entityCollection.get(id);
                if (entity === null) {
                    entity = this.resultCollection.get(id);
                }

                newEntityCollection.push(deepCopyObject(entity));
            });

            this.$emit('change', newEntityCollection);
        },

        addItem(item) {
            if (this.isSelected(item)) {
                this.remove(item);
                return Promise.resolve();
            }

            this.$emit('item-add', item);

            this.selectedIds = [...this.selectedIds, item.id];

            this.$refs.selectionList.select();
            this.$refs.selectionList.focus();

            if (this.localMode) {
                this.totalAssigned += 1;
                return Promise.resolve();
            }

            this.isLoading = true;

            return this.repository.assign(item.id, this.entityCollection.context).then((response) => {
                this.totalAssigned += 1;
                this.isLoading = false;
                return response;
            });
        },

        remove(item) {
            this.$emit('item-remove', item);

            if (this.localMode) {
                this.removeIdFromList(item.id);
                return Promise.resolve();
            }

            this.isLoading = true;
            return this.repository.delete(item.id, this.entityCollection.context).then((response) => {
                this.removeIdFromList(item.id);
                this.isLoading = false;
                return response;
            });
        },

        removeIdFromList(id) {
            this.totalAssigned -= 1;
            this.selectedIds = this.selectedIds.filter((currentId) => {
                return currentId !== id;
            });
        },

        resetSearchCriteria() {
            this.searchCriteria.setPage(1);
            this.searchCriteria.setTerm(this.searchTerm);
            this.searchCriteria.setLimit(this.resultLimit);
        },

        onSelectExpanded() {
            this.resetSearchCriteria();
            this.resultCollection = null;

            this.sendSearchRequest().then(() => {
                this.resetActiveItem();
            });

            this.$refs.selectionList.focus();
        },

        onSelectCollapsed() {
            this.$refs.selectionList.blur();
        },

        onSearchTermChange(term) {
            this.searchTerm = term;
            this.$emit('search-term-change', term);
            this.debouncedSearch(term);
        },

        resetActiveItem() {
            this.$refs.swSelectResultList.setActiveItemIndex(0);
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.search();
        }, 400),

        // Used to reset the results. Normally all search results are appended to the results list, because of pagination.
        // Useful when the criteria changes from outside and we have to search again because of changed filters for example.
        resetResultCollection() {
            this.resultCollection = null;

            // Direct new search if the select field is expanded
            if (this.$refs.selectBase.expanded) {
                this.sendSearchRequest();
            }
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },
    },
});
