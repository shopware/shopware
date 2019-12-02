import './sw-entity-single-select.scss';
import template from './sw-entity-single-select.html.twig';

const { Component, Utils } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { debounce, get } = Shopware.Utils;

Component.register('sw-entity-single-select', {
    template,

    model: {
        prop: 'value',
        event: 'change'
    },

    inject: { repositoryFactory: 'repositoryFactory' },

    props: {
        value: {
            required: true
        },
        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        labelProperty: {
            type: String,
            required: false,
            default: 'name'
        },
        entity: {
            required: true,
            type: String
        },
        resultLimit: {
            type: Number,
            required: false,
            default: 25
        },
        criteria: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, this.resultLimit);
            }
        },
        context: {
            type: Object,
            required: false,
            default() {
                return Shopware.Context.api;
            }
        },
        popoverConfig: {
            type: Object,
            required: false,
            default() {
                return {
                    active: false
                };
            }
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            resultCollection: null,
            singleSelection: null,
            isLoading: false,
            // used to track if an item was selected before closing the result list
            itemRecentlySelected: false,
            lastSelection: null
        };
    },

    computed: {
        inputClasses() {
            return {
                'is--expanded': this.isExpanded
            };
        },

        selectionTextClasses() {
            return {
                'is--placeholder': !this.singleSelection
            };
        },
        repository() {
            return this.repositoryFactory.create(this.entity);
        },

        /**
         * Returns the resultCollection with the actual selection as first entry
         * @returns {EntityCollection}
         */
        results() {
            if (this.singleSelection && this.resultCollection) {
                const collection = this.createCollection(this.resultCollection);
                collection.push(this.singleSelection);
                this.resultCollection.forEach((item) => {
                    if (item.id !== this.singleSelection.id) {
                        collection.add(item);
                    }
                });
                return collection;
            }

            return this.resultCollection;
        }
    },

    watch: {
        value(value) {
            // No need to fetch again when the new value is the last one we selected
            if (this.lastSelection && this.value === this.lastSelection.id) {
                this.singleSelection = this.lastSelection;
                this.lastSelection = null;
                return;
            }

            if (value === '' || value === null) {
                this.singleSelection = null;
                return;
            }

            this.loadSelected();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadSelected();
        },

        /**
         * Fetches the selected entity from the server
         */
        loadSelected() {
            if (this.value === '' || this.value === null) {
                return Promise.resolve();
            }

            this.isLoading = true;
            return this.repository.get(this.value, this.context).then((item) => {
                this.singleSelection = item;
                this.isLoading = false;
                return item;
            });
        },

        createCollection(collection) {
            return new EntityCollection(collection.source, collection.entity, collection.criteria);
        },

        isSelected(item) {
            return item.id === this.value;
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.search();
        }, 400),

        search() {
            if (this.criteria.term === this.searchTerm) {
                return Promise.resolve();
            }

            this.criteria.setPage(1);
            this.criteria.setLimit(this.resultLimit);
            this.criteria.setTerm(this.searchTerm);
            this.resultCollection = null;

            const searchPromise = this.loadData().then(() => {
                this.resetActiveItem();
            });
            this.$emit('search', searchPromise);

            return searchPromise;
        },

        paginate() {
            if (!this.resultCollection || this.resultCollection.total < this.criteria.page * this.criteria.limit) {
                return;
            }

            this.criteria.setPage(this.criteria.page + 1);

            this.loadData();
        },

        loadData() {
            this.isLoading = true;

            return this.repository.search(this.criteria, this.context).then((result) => {
                this.displaySearch(result);

                this.isLoading = false;

                return result;
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

        onSelectExpanded() {
            this.isExpanded = true;
            // Always start with a fresh list when opening the result list
            this.criteria.setPage(1);
            this.criteria.setLimit(this.resultLimit);
            this.criteria.setTerm('');
            this.resultCollection = null;

            this.loadData().then(() => {
                this.resetActiveItem();
            });

            // Get the search text of the selected item as prefilled value
            this.searchTerm = this.tryGetSearchText(this.singleSelection);

            this.$nextTick(() => {
                this.$refs.swSelectInput.select();
                this.$refs.swSelectInput.focus();
            });
        },

        tryGetSearchText(option) {
            let searchText = this.getKey(option, this.labelProperty, '');
            if (!searchText) {
                searchText = this.getKey(option, `translated.${this.labelProperty}`, '');
            }
            return searchText;
        },

        onSelectCollapsed() {
            // Empty the selection if the search term is empty
            if (this.searchTerm === '' && !this.itemRecentlySelected) {
                this.clearSelection();
            }
            this.$refs.swSelectInput.blur();
            this.searchTerm = '';
            this.itemRecentlySelected = false;
            this.isExpanded = false;
        },


        closeResultList() {
            this.$refs.selectBase.collapse();
        },

        setValue(item) {
            this.itemRecentlySelected = true;
            this.closeResultList();

            // This is a little against v-model. But so we dont need to load the selected item on every selection
            // from the server
            this.lastSelection = item;
            /** @deprecated Html select don't have an onInput event */
            this.$emit('input', item.id, item);
            this.$emit('change', item.id, item);

            this.$emit('option-select', Utils.string.camelCase(this.entity), item);
        },

        clearSelection() {
            this.$emit('before-selection-clear', this.singleSelection, this.value);
            /** @deprecated Html select don't have an onInput event */
            this.$emit('input', null);
            this.$emit('change', null);

            this.$emit('option-select', Utils.string.camelCase(this.entity), null);
        },

        resetActiveItem(pos = 0) {
            // Return if the result list is closed before the search request returns
            if (!this.$refs.resultsList) {
                return;
            }
            // If an item is selected the second entry is the first search result
            if (this.singleSelection) {
                pos = 1;
            }
            this.$refs.resultsList.setActiveItemIndex(pos);
        },

        onInputSearchTerm() {
            this.debouncedSearch();
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        }
    }
});
