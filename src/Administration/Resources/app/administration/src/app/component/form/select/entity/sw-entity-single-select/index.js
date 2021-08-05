import './sw-entity-single-select.scss';
import template from './sw-entity-single-select.html.twig';

const { Component, Mixin, Utils } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { debounce, get } = Shopware.Utils;

Component.register('sw-entity-single-select', {
    template,

    inject: { repositoryFactory: 'repositoryFactory', feature: 'feature' },

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
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
        resetOption: {
            type: String,
            required: false,
            default: '',
        },
        labelProperty: {
            type: [String, Array],
            required: false,
            default: 'name',
        },
        labelCallback: {
            type: Function,
            required: false,
            default: null,
        },
        entity: {
            required: true,
            type: String,
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
        context: {
            type: Object,
            required: false,
            default() {
                return Shopware.Context.api;
            },
        },

        disableAutoClose: {
            type: Boolean,
            required: false,
            default: false,
        },
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
            lastSelection: null,
        };
    },

    computed: {
        inputClasses() {
            return {
                'is--expanded': this.isExpanded,
            };
        },

        selectionTextClasses() {
            return {
                'is--placeholder': !this.singleSelection,
            };
        },
        repository() {
            return this.repositoryFactory.create(this.entity);
        },

        /**
         * @returns {EntityCollection}
         */
        results() {
            return this.resultCollection;
        },
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
        },
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
            if (!this.value) {
                if (this.resetOption) {
                    this.singleSelection = {
                        id: null,
                        name: this.resetOption,
                    };
                }

                return Promise.resolve();
            }

            this.isLoading = true;

            return this.repository.get(this.value, { ...this.context, inheritance: true }, this.criteria).then((item) => {
                this.criteria.setIds([]);

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

            return this.repository.search(this.criteria, { ...this.context, inheritance: true }).then((result) => {
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

            if (this.resetOption) {
                if (!this.resultCollection.has(null)) {
                    this.resultCollection.unshift({
                        id: null,
                        name: this.resetOption,
                    });
                }
            }
        },

        displayLabelProperty(item) {
            if (typeof this.labelCallback === 'function') {
                return this.labelCallback(item);
            }

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
            if (typeof this.labelCallback === 'function') {
                return this.labelCallback(option);
            }
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

            if (!this.disableAutoClose) {
                this.closeResultList();
            }

            // This is a little against v-model. But so we dont need to load the selected item on every selection
            // from the server
            this.lastSelection = item;
            this.$emit('change', item.id, item);

            this.$emit('option-select', Utils.string.camelCase(this.entity), item);
        },

        clearSelection() {
            this.$emit('before-selection-clear', this.singleSelection, this.value);
            this.$emit('change', null);

            this.$emit('option-select', Utils.string.camelCase(this.entity), null);
        },

        clearInput() {
            this.searchTerm = '';
            this.clearSelection();
            this.$refs.selectBase.collapse();
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

        onInputSearchTerm(event) {
            const value = event.target.value;

            this.$emit('search-term-change', value);
            this.debouncedSearch();
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },
    },
});
