import './sw-entity-single-select.scss';
import template from './sw-entity-single-select.html.twig';

const { Component, Mixin, Utils } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { debounce, get } = Shopware.Utils;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-entity-single-select', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('remove-api-error'),
        Mixin.getByName('notification'),
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
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
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
            default: () => Shopware.Context.api,
        },
        selectionDisablingMethod: {
            type: Function,
            required: false,
            default: () => false,
        },
        disableAutoClose: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabledSelectionTooltip: {
            type: Object,
            required: false,
            default: () => {
                return { message: '' };
            },
        },
        descriptionPosition: {
            type: String,
            required: false,
            default: 'right',
            validValues: ['bottom', 'right', 'left'],
            validator(value) {
                return ['bottom', 'right', 'left'].includes(value);
            },
        },
        allowEntityCreation: {
            type: Boolean,
            required: false,
            default: false,
        },
        entityCreationLabel: {
            type: String,
            required: false,
            default() {
                return this.$tc('global.sw-single-select.labelEntity');
            },
        },
        advancedSelectionComponent: {
            type: String,
            required: false,
            default: '',
        },
        advancedSelectionParameters: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
        displayVariants: {
            type: Boolean,
            required: false,
            default: false,
        },
        shouldShowActiveState: {
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
            entityExists: true,
            newEntityName: '',
            isAdvancedSelectionModalVisible: false,
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

        isAdvancedSelectionActive() {
            return this.advancedSelectionComponent && Component.getComponentRegistry().has(this.advancedSelectionComponent);
        },

        advancedSelectionInitialSearchTerm() {
            if (this.singleSelection && this.tryGetSearchText(this.singleSelection) === this.searchTerm) {
                return '';
            }

            return this.searchTerm;
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
                if (!item) {
                    this.$emit('change', null);
                }

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
                if (this.allowEntityCreation) {
                    this.filterSearchGeneratedTags();
                }
                return Promise.resolve();
            }

            if (!this.allowEntityCreation) {
                return this.handleSearchPromise();
            }

            this.isLoading = true;
            return this.checkEntityExists(this.searchTerm).then(() => {
                if (!this.entityExists && this.searchTerm) {
                    const criteria = new Criteria(1, this.resultLimit);
                    criteria.addFilter(
                        Criteria.contains('name', this.searchTerm),
                    );

                    return this.repository.search(criteria, {
                        ...this.context,
                        inheritance: true,
                    }).then((result) => {
                        this.resultCollection = result;

                        const newEntity = this.repository.create(this.context, -1);
                        newEntity.name = this.$tc(
                            'global.sw-single-select.labelEntityAdd',
                            0,
                            {
                                term: this.searchTerm,
                                entity: this.entityCreationLabel,
                            },
                        );

                        this.resultCollection.unshift(newEntity);

                        this.newEntityName = this.searchTerm;
                        this.displaySearch(this.resultCollection);
                        this.isLoading = false;

                        return Promise.resolve();
                    });
                }
                return this.handleSearchPromise();
            });
        },

        handleSearchPromise() {
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

        checkEntityExists(term) {
            // Set existing entity to true to display all manufacturers when no search term is given
            if (term.trim().length === 0) {
                this.entityExists = true;
                return Promise.resolve();
            }

            const criteria = new Criteria(1, this.resultLimit);
            criteria.addIncludes({
                [this.entity]: ['id', 'name'],
            });
            criteria.addFilter(
                Criteria.equals('name', term),
            );

            return this.repository.search(criteria, this.context).then((response) => {
                this.entityExists = response.total > 0;

                return response.total > 0;
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

            // Add new entity if not exists yet
            if (this.allowEntityCreation && !this.entityExists && item.id === -1) {
                return this.addItem(item);
            }

            // This is a little against v-model. But so we don't need to load the selected item on every selection
            // from the server
            this.lastSelection = item;
            this.$emit('change', item.id, item);

            this.$emit('option-select', Utils.string.camelCase(this.entity), item);
            return null;
        },

        addItem(item) {
            if (!this.allowEntityCreation) {
                return null;
            }

            if (item.id === -1) {
                this.createNewEntity();
            } else {
                this.$super('addItem', item);
            }
            return null;
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

        isSelectionDisabled(selection) {
            if (this.disabled) {
                return true;
            }

            return this.selectionDisablingMethod(selection);
        },

        getDisabledSelectionTooltip(selection) {
            return {
                ...this.disabledSelectionTooltip,
                disabled: this.disabledSelectionTooltip.disabled || !this.selectionDisablingMethod(selection),
            };
        },

        createNewEntity() {
            const entity = this.repository.create(this.context);
            entity.name = this.newEntityName;

            this.repository.save(entity, this.context).then(() => {
                this.lastSelection = entity;
                this.$emit('change', entity.id, entity);

                this.$emit('option-select', Utils.string.camelCase(this.entity), entity);
                this.createNotificationSuccess({
                    message: this.$tc(
                        'global.sw-single-select.labelEntityAddedSuccess',
                        0,
                        {
                            term: entity.name,
                            entity: this.entityCreationLabel,
                        },
                    ),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessage', 0, { entityName: this.entity }),
                });
                Shopware.Utils.debug.error('Only Entities with "name" as the only required field are creatable.');
                this.isLoading = false;
            });
        },

        filterSearchGeneratedTags() {
            this.resultCollection = this.resultCollection.filter(entity => {
                return entity.id !== -1;
            });
        },

        openAdvancedSelectionModal() {
            this.isAdvancedSelectionModalVisible = true;
        },

        closeAdvancedSelectionModal() {
            this.isAdvancedSelectionModalVisible = false;
        },

        onAdvancedSelectionSubmit(selectedItems) {
            if (selectedItems.length > 0) {
                this.setValue(selectedItems[0]);
            }
        },

        getActiveIconColor(item) {
            if (item?.active) {
                return item.active === true ? '#37d046' : '#d1d9e0';
            }

            return '#d1d9e0';
        },
    },
});
