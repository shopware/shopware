import { Component } from 'src/core/shopware';
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

        currentSearchTerm() {
            return this.searchTerm;
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
            // TODO 1. Load selections initially (terms query with multiple IDs)
            // TODO 2. On adding use local selection array with id and name

            // const criteria = [];
            // const params = {};
            //
            // criteria.push(CriteriaFactory.term('customer_address.customerId', this.customerId));
            // params.criteria = CriteriaFactory.nested('AND', ...criteria);
            //
            // this.serviceProvider.getList(0, 200, {
            //
            // }).then((response) => {
            //     console.log(response.data);
            // });
        },

        loadPreviewResults() {
            this.isLoading = true;

            this.serviceProvider.getList(0, this.previewResultsLimit).then((response) => {
                this.results = response.data;
                this.isLoading = false;
            });
        },

        closeOnClickOutside(event) {
            const target = event.target;

            if (target.closest('.sw-multi-select') === null) {
                this.isExpanded = false;
                this.activeResultPosition = 0;
            }
        },

        getCategoryEntry(id) {
            return this.results.find((entry) => {
                return entry.id === id;
            });
        },

        setActiveResultPosition(index) {
            this.activeResultPosition = index;
        },

        dismissSelection(id) {
            if (!id) {
                return;
            }

            this.selections = this.selections.filter((entry) => entry.id !== id);

            this.$emit('input', this.selections);

            this.setFocus();
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

        loadResults() {
            this.timeout = setTimeout(() => {
                this.serviceProvider.getList(0, this.resultsLimit, { term: this.searchTerm }).then((response) => {
                    this.results = response.data;
                    this.isLoading = false;

                    this.scrollToResultsTop();
                });
            }, this.searchDelayTime);
        },

        openResultList() {
            this.isExpanded = true;
        },

        closeResultList() {
            this.isExpanded = false;
            this.$refs.swMultiSelectInput.blur();
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
            const swMultiSelectEl = this.$refs.swMultiSelect;
            swMultiSelectEl.querySelector('.sw-multi-select__results').scrollTop = 0;
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

        dismissLastSelection() {
            if (this.searchTerm.length > 0) {
                return;
            }

            if (!this.selections.length) {
                return;
            }

            const lastSelectionId = this.selections.slice(-1)[0].id;

            this.dismissSelection(lastSelectionId);
        },

        setFocus() {
            this.$refs.swMultiSelectInput.focus();
        },

        isSelected(result) {
            return !this.selections.every((item) => {
                return item.id !== result.id;
            });
        },

        addSelection(result) {
            if (!result.id || !result.name) {
                return;
            }

            if (this.isSelected(result)) {
                return;
            }

            this.selections.push({ id: result.id, name: result.name });
            this.searchTerm = '';

            this.$emit('input', this.selections.map((item) => item.id));

            this.setFocus();
        }
    }
});
