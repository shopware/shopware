import { Component, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import './sw-multi-select.less';
import template from './sw-multi-select.html.twig';

Component.register('sw-multi-select', {
    template,

    mixins: [
        Mixin.getByName('validation')
    ],

    props: {
        serviceProvider: {
            type: Object,
            required: true
        },
        criteria: {
            type: Object,
            required: false,
            default: null
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        value: {
            type: Array,
            required: true
        },
        label: {
            type: String,
            default: ''
        },
        id: {
            type: String,
            required: true
        },
        previewResultsLimit: {
            type: Number,
            required: false,
            default: 25
        },
        resultsLimit: {
            type: Number,
            required: false,
            default: 25
        },
        entityName: {
            type: String,
            required: false,
            default: 'category'
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        store: {
            type: Object,
            required: true
        },
        defaultItemId: {
            type: String,
            required: false
        }
    },

    data() {
        return {
            initialSelection: false,
            searchTerm: '',
            isExpanded: false,
            results: [],
            selections: [],
            activeResultPosition: 0,
            isLoading: false,
            hasError: false
        };
    },

    computed: {
        multiSelectClasses() {
            return {
                'has--error': !this.isValid || this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded
            };
        }
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },

        disabled: 'loadPreviewResults',
        criteria: 'loadPreviewResults'
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.selections = [];
            this.results = [];

            if (!this.disabled) {
                this.loadPreviewResults();
            }
            this.loadSelections();
            this.addEventListeners();
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            document.addEventListener('click', this.closeOnClickOutside);
        },

        removeEventListeners() {
            document.removeEventListener('click', this.closeOnClickOutside);
        },

        loadSelections() {
            this.isLoading = true;

            this.store.getList({
                page: 1,
                limit: 500 // ToDo: The concept of assigning a large amount of relations needs a special solution.
            }).then((response) => {
                this.selections = response.items;
                this.isLoading = false;
            });
        },

        loadResults() {
            this.serviceProvider.getList({
                page: 1,
                limit: this.resultsLimit,
                term: this.searchTerm,
                criteria: this.criteria
            }).then((response) => {
                this.results = response.data;
                this.isLoading = false;

                this.scrollToResultsTop();
            });
        },

        loadPreviewResults() {
            this.isLoading = true;

            this.serviceProvider.getList({
                page: 1,
                limit: this.previewResultsLimit,
                criteria: this.criteria
            }).then((response) => {
                this.results = response.data;
                this.isLoading = false;
            });
        },

        openResultList() {
            this.isExpanded = true;
        },

        closeResultList() {
            this.isExpanded = false;
            this.$refs.swMultiSelectInput.blur();
        },

        onSearchTermChange() {
            this.isLoading = true;

            this.doGlobalSearch();
        },

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            if (this.searchTerm.length > 0) {
                this.loadResults();
            } else {
                this.loadPreviewResults();
                this.scrollToResultsTop();
            }
        }, 400),

        setActiveResultPosition(index) {
            this.activeResultPosition = index;
        },

        navigateUpResults() {
            if (this.activeResultPosition === 0) {
                return;
            }

            this.activeResultPosition = this.activeResultPosition - 1;

            const swMultiSelectEl = this.$refs.swMultiSelect;
            const resultItem = swMultiSelectEl.querySelector('.sw-multi-select__result-item');
            const resultContainer = swMultiSelectEl.querySelector('.sw-multi-select__results');

            resultContainer.scrollTop -= resultItem.offsetHeight;
        },

        navigateDownResults() {
            if (this.activeResultPosition === this.results.length - 1) {
                return;
            }

            this.activeResultPosition = this.activeResultPosition + 1;

            const swMultiSelectEl = this.$refs.swMultiSelect;
            const activeItem = swMultiSelectEl.querySelector('.is--active');
            const itemHeight = swMultiSelectEl.querySelector('.sw-multi-select__result-item').offsetHeight;
            const activeItemPosition = activeItem.offsetTop + itemHeight;
            const resultContainer = swMultiSelectEl.querySelector('.sw-multi-select__results');
            let resultContainerHeight = resultContainer.offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                resultContainer.scrollTop += itemHeight;
            }
        },

        scrollToResultsTop() {
            this.activeResultPosition = 0;
            this.$refs.swMultiSelect.querySelector('.sw-multi-select__results').scrollTop = 0;
        },

        setFocus() {
            this.$refs.swMultiSelectInput.focus();
        },

        closeOnClickOutside(event) {
            const target = event.target;

            if (target.closest('.sw-multi-select') !== this.$refs.swMultiSelect) {
                this.isExpanded = false;
                this.activeResultPosition = 0;
            }
        },

        isInSelections(result) {
            return !this.selections.every((item) => {
                return item.id !== result.id;
            });
        },

        addSelection(result) {
            if (!result.id || !result.name) {
                return;
            }

            if (this.isInSelections(result)) {
                return;
            }

            this.selections.push(result);
            this.searchTerm = '';

            this.emitChanges(this.selections);

            this.setFocus();

            if (this.selections.length === 1) {
                this.changeDefaultItemId(result.id);
            }
        },

        addSelectionOnEnter() {
            const activeItem = this.results[this.activeResultPosition];
            const id = activeItem.id;

            if (!id) {
                return;
            }

            const result = this.results.filter((entry) => entry.id === id);

            if (!result.length) {
                return;
            }

            this.addSelection(result[0]);
        },

        onDismissSelection(id) {
            this.dismissSelection(id);
            this.setFocus();
        },

        dismissSelection(id) {
            if (!id) {
                return;
            }

            this.selections = this.selections.filter((entry) => entry.id !== id);

            this.emitChanges(this.selections);

            if (this.defaultItemId && this.defaultItemId === id) {
                if (this.selections.length >= 1) {
                    this.changeDefaultItemId(this.selections[0].id);
                } else {
                    this.changeDefaultItemId(null);
                }
            }
        },

        dismissLastSelection() {
            if (this.searchTerm.length > 0) {
                return;
            }

            if (!this.selections.length) {
                return;
            }

            const lastSelectionId = this.selections[this.selections.length - 1].id;

            this.dismissSelection(lastSelectionId);
        },

        emitChanges(items) {
            const itemIds = items.map((item) => item.id);
            const associationStore = this.store;

            // Delete existing relations
            Object.keys(associationStore.store).forEach((id) => {
                if (!itemIds.includes(id)) {
                    associationStore.store[id].delete();
                }
            });

            // Add new relations
            items.forEach((item) => {
                if (!associationStore.store[item.id]) {
                    associationStore.create(item.id, item, true);
                }

                // In case the entity was already created but was deleted before
                associationStore.store[item.id].isDeleted = false;
            });

            this.$emit('input', this.selections);
        },

        changeDefaultItemId(id) {
            if (typeof this.defaultItemId !== 'undefined') {
                this.$emit('default_changed', id);
            }
        }
    }
});
