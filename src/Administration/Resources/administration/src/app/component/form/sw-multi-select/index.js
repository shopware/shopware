import { Component } from 'src/core/shopware';
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
            type: Array
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
        searchDelayTime: {
            type: Number,
            required: false,
            default: 400
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
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            results: [],
            selections: [],
            activeResultPosition: 0,
            isLoading: false,
            hasError: false,
            timeout: null
        };
    },

    computed: {
        filteredResults() {
            return this.results;
        },

        displaySelections() {
            return this.selections;
        },

        multiSelectClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded
            };
        }
    },

    watch: {
        searchTerm: 'onSearchTermChange'
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
                CriteriaFactory.terms(`${this.entityName}.id`, this.value)
            );

            this.serviceProvider.getList(0, this.value.length, {
                filter: [criteria.getQuery()]
            }).then((response) => {
                this.selections = response.data;
                this.isLoading = false;
            });
        },

        loadResults() {
            this.timeout = setTimeout(() => {
                this.serviceProvider.getList(0, this.resultsLimit, { term: this.searchTerm }).then((response) => {
                    this.results = response.data;
                    this.isLoading = false;

                    this.scrollToResultsTop();
                });
            }, this.searchDelayTime);
        },

        loadPreviewResults() {
            this.isLoading = true;

            this.serviceProvider.getList(0, this.previewResultsLimit).then((response) => {
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
            this.activeResultPosition = 0;
            this.isLoading = true;

            clearTimeout(this.timeout);

            if (this.searchTerm.length > 0) {
                this.loadResults();
            } else {
                this.loadPreviewResults();
                this.scrollToResultsTop();
            }
        },

        setActiveResultPosition(index) {
            this.activeResultPosition = index;
        },

        navigateUpResults() {
            if (this.activeResultPosition === 0) {
                return;
            }

            this.activeResultPosition = this.activeResultPosition - 1;

            const itemHeight = this.$refs.swMultiSelect.querySelector('.sw-multi-select__result-item').offsetHeight;
            this.$refs.swMultiSelect.querySelector('.sw-multi-select__results').scrollTop -= itemHeight;
        },

        navigateDownResults() {
            if (this.activeResultPosition === this.filteredResults.length - 1) {
                return;
            }

            this.activeResultPosition = this.activeResultPosition + 1;

            const swMultiSelectEl = this.$refs.swMultiSelect;
            const activeItem = swMultiSelectEl.querySelector('.is--selected');
            const itemHeight = swMultiSelectEl.querySelector('.sw-multi-select__result-item').offsetHeight;
            const activeItemPosition = activeItem.offsetTop + itemHeight;
            let resultContainerHeight = this.$refs.swMultiSelect.querySelector('.sw-multi-select__results').offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                swMultiSelectEl.querySelector('.sw-multi-select__results').scrollTop += itemHeight;
            }
        },

        scrollToResultsTop() {
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

            this.selections.push({ id: result.id, name: result.name });
            this.searchTerm = '';

            this.$emit('input', this.selections.map((item) => item.id));

            this.setFocus();
        },

        addSelectionOnEnter() {
            const activeItem = this.$refs.swMultiSelect.querySelector('.is--selected');
            const id = activeItem.dataset.id;

            if (!id) {
                return;
            }

            const result = this.results.filter((entry) => entry.id === id);

            if (!result.length) {
                return;
            }

            // TODO: First array item?
            this.addSelection(result[0]);
        },

        dismissSelection(id) {
            if (!id) {
                return;
            }

            this.selections = this.selections.filter((entry) => entry.id !== id);

            this.$emit('input', this.selections);

            this.setFocus();
        },

        dismissLastSelection() {
            if (this.searchTerm.length > 0) {
                return;
            }

            if (!this.selections.length) {
                return;
            }

            const lastSelectionId = this.selections.slice(-1)[0].id;

            this.dismissSelection(lastSelectionId);
        }
    }
});
