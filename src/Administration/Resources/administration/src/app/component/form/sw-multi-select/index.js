import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import './sw-multi-select.less';
import template from './sw-multi-select.html.twig';

Component.register('sw-multi-select', {
    template,

    props: {
        serviceProvider: {
            type: Object,
            required: true
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
            default: 20
        },
        resultsLimit: {
            type: Number,
            required: false,
            default: 200
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
                'has--error': this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded
            };
        }
    },

    watch: {
        value() {
            if (this.initialSelection) {
                return;
            }
            this.initialSelection = true;
            this.loadSelections();
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.loadPreviewResults();
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
            if (!this.value.length) {
                return;
            }

            this.isLoading = true;

            const criteria = CriteriaFactory.nested(
                'AND',
                CriteriaFactory.terms(`${this.entityName}.id`, this.value.map((item) => {
                    return item.id;
                }))
            );

            this.serviceProvider.getList({
                page: 1,
                limit: this.value.length,
                additionalParams: {
                    filter: [criteria.getQuery()]
                }
            }).then((response) => {
                this.selections = response.data;
                this.isLoading = false;
            });
        },

        loadResults() {
            this.serviceProvider.getList({
                page: 1,
                limit: this.resultsLimit,
                additionalParams: {
                    term: this.searchTerm
                }
            }).then((response) => {
                this.results = response.data;
                this.isLoading = false;

                this.scrollToResultsTop();
            });
        },

        loadPreviewResults() {
            this.isLoading = true;

            this.serviceProvider.getList({ page: 1, limit: this.previewResultsLimit }).then((response) => {
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

            if (target.closest('.sw-multi-select') === null) {
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

        dismissSelection(id) {
            if (!id) {
                return;
            }

            this.selections = this.selections.filter((entry) => entry.id !== id);

            this.emitChanges(this.selections);

            this.setFocus();
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

            Object.keys(associationStore.store).forEach((id) => {
                const associationEntity = associationStore.store[id];

                if (itemIds.includes(id)) {
                    associationStore.addAddition(associationEntity);
                } else {
                    associationStore.store[id].delete();
                }
            });

            items.forEach((item) => {
                const id = item.id;
                const associationEntity = associationStore.store[id];

                if (!associationEntity) {
                    associationStore.create(id, item, true);
                }
            });

            this.$emit('input', this.selections);
        }
    }
});
