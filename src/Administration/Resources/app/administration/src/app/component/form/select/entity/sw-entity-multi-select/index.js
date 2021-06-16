import template from './sw-entity-multi-select.html.twig';

const { Component, Mixin } = Shopware;
const { debounce, get } = Shopware.Utils;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('sw-entity-multi-select', {
    template,
    inheritAttrs: false,

    inject: { repositoryFactory: 'repositoryFactory' },

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'entityCollection',
        event: 'change',
    },

    props: {
        labelProperty: {
            type: [String, Array],
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

        placeholder: {
            type: String,
            required: false,
            default: '',
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

        entityCollection: {
            type: Array,
            required: true,
        },

        entityName: {
            type: String,
            required: false,
            default: null,
        },

        context: {
            type: Object,
            required: false,
            default() {
                return Shopware.Context.api;
            },
        },
        hideLabels: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            searchTerm: '',
            limit: this.valueLimit,
            searchCriteria: null,
            isLoading: false,
            currentCollection: null,
            resultCollection: null,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create(this.entityName || this.entityCollection.entity);
        },

        visibleValues() {
            if (!this.currentCollection || this.currentCollection.length <= 0) {
                return [];
            }

            return this.currentCollection.slice(0, this.limit);
        },


        totalValuesCount() {
            if (this.currentCollection.length) {
                return this.currentCollection.length;
            }

            return 0;
        },

        invisibleValueCount() {
            if (!this.currentCollection) {
                return 0;
            }

            return Math.max(0, this.totalValuesCount - this.limit);
        },
    },

    watch: {
        entityCollection() {
            this.refreshCurrentCollection();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.refreshCurrentCollection();
        },

        refreshCurrentCollection() {
            this.currentCollection = EntityCollection.fromCollection(this.entityCollection);
        },

        createEmptyCollection() {
            return new EntityCollection(
                this.entityCollection.source,
                this.entityCollection.entity,
                this.entityCollection.context,
                this.entityCollection.criteria,
            );
        },

        isSelected(item) {
            return this.currentCollection.has(item.id);
        },

        loadData() {
            this.isLoading = true;

            return this.repository.search(this.criteria, { ...this.context, inheritance: true }).then((result) => {
                this.displaySearch(result);

                this.isLoading = false;

                return result;
            });
        },

        search() {
            if (this.criteria.term === this.searchTerm) {
                return Promise.resolve();
            }

            this.resetCriteria();
            this.resultCollection = null;

            const searchPromise = this.loadData().then((result) => {
                this.resetActiveItem();
                return result;
            });
            this.$emit('search', searchPromise);

            return searchPromise;
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

        displayLabelProperty(item) {
            const labelProperties = [];

            if (Array.isArray(this.labelProperty)) {
                labelProperties.push(...this.labelProperty);
            } else {
                labelProperties.push(this.labelProperty);
            }

            return labelProperties.map(labelProperty => {
                return this.getKey(item, labelProperty) || this.getKey(item, `translated.${labelProperty}`);
            }).join(' ');
        },

        resetActiveItem() {
            this.$refs.swSelectResultList.setActiveItemIndex(0);
        },

        resetCriteria() {
            this.criteria.setPage(1);
            this.criteria.setLimit(this.resultLimit);
            this.criteria.setTerm(this.searchTerm);
        },

        paginate() {
            if (!this.resultCollection || this.resultCollection.total < this.criteria.page * this.criteria.limit) {
                return;
            }

            this.criteria.setPage(this.criteria.page + 1);

            this.loadData();
        },

        emitChanges(newCollection) {
            this.$emit('change', newCollection);
        },

        addItem(item) {
            if (this.isSelected(item)) {
                this.remove(item);
                return;
            }

            this.$emit('item-add', item);

            const newCollection = EntityCollection.fromCollection(this.currentCollection);
            newCollection.add(item);

            this.emitChanges(newCollection);

            this.$refs.selectionList.focus();
            this.$refs.selectionList.select();
        },

        remove(item) {
            this.$emit('item-remove', item);

            const newCollection = EntityCollection.fromCollection(this.currentCollection);
            newCollection.remove(item.id);

            this.emitChanges(newCollection);
        },

        removeLastItem() {
            if (!this.currentCollection.length) {
                return;
            }

            if (this.invisibleValueCount > 0) {
                this.expandValueLimit();
                return;
            }

            const lastSelection = this.currentCollection[this.currentCollection.length - 1];
            this.remove(lastSelection);
        },

        onSelectExpanded() {
            this.resetCriteria();
            this.resultCollection = null;

            this.loadData();

            this.$refs.selectionList.focus();
        },

        onSelectCollapsed() {
            this.searchTerm = '';
            this.$refs.selectionList.blur();
        },

        expandValueLimit() {
            this.$emit('display-values-expand');

            this.limit += this.limit;
        },

        onSearchTermChange(term) {
            this.searchTerm = term;
            this.$emit('search-term-change', term);
            this.debouncedSearch();
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
                this.loadData();
            }
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },
    },
});
