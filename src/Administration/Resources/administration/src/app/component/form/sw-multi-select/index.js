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
            results: [], // entries
            selections: [], // values
            activeResultPosition: 0,
            isLoading: false,
            timeout: null
        };
    },

    computed: {
        filteredResults() {
            return this.results;
        },

        displaySelections() {
            // TODO 1. Load selections initially (terms query with multiple IDs)
            // TODO 2. On adding use local selection array with id and name
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
            this.addEventListeners();
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            document.addEventListener('keyup', this.handleKeyUpActions);
            document.addEventListener('keydown', this.handleKeyDownActions);
            document.addEventListener('click', this.closeOnClickOutside);
        },

        removeEventListeners() {
            document.removeEventListener('keyup', this.handleKeyUpActions);
            document.removeEventListener('keydown', this.handleKeyDownActions);
            document.removeEventListener('click', this.closeOnClickOutside);
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

        onDismissSelection(id) {
            if (!id) {
                return;
            }

            // Remove the field from the value attribute of the hidden field
            this.selections = this.selections.filter((entry) => entry.id !== id);

            // Emit change for v-model support
            this.$emit('input', this.selections);

            this.setFocus();
        },

        onSearchTermChange() {
            this.activeResultPosition = 0;
            this.isLoading = true;

            clearTimeout(this.timeout);

            if (this.searchTerm.length > 0) {
                this.timeout = setTimeout(() => {
                    this.serviceProvider.getList(0, this.resultsLimit, { term: this.searchTerm }).then((response) => {
                        this.results = response.data;
                        this.isLoading = false;
                    });
                }, this.searchDelayTime);
            } else {
                this.loadPreviewResults();
            }
        },

        openResultList() {
            this.isExpanded = true;
        },

        closeResultList() {
            this.isExpanded = false;
            this.$refs.swMultiSelectInput.blur();
        },

        handleKeyUpActions(event) {
            const keyEnter = 13;
            const keyBackspace = 8;
            const keyEsc = 27;

            if (!this.isExpanded) {
                return;
            }

            if (event.keyCode === keyEnter) {
                this.addItemOnEnter();
            }

            if (event.keyCode === keyBackspace) {
                this.deleteItemOnBackspace();
            }

            if (event.keyCode === keyEsc) {
                this.closeResultList();
                this.$refs.swMultiSelectInput.blur();
            }
        },

        handleKeyDownActions(event) {
            const keyArrowUp = 38;
            const keyArrowDown = 40;

            if (!this.isExpanded) {
                return;
            }

            if (event.keyCode === keyArrowUp) {
                this.navigateUpResults();
            } else if (event.keyCode === keyArrowDown) {
                this.navigateDownResults();
            }
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

            const activeItem = this.$refs.swMultiSelect.querySelector('.is--selected');
            const itemHeight = activeItem.offsetHeight;
            const activeItemPosition = activeItem.offsetTop + itemHeight;
            let resultContainerHeight = this.$refs.swMultiSelect.querySelector('.sw-multi-select__results').offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                this.$refs.swMultiSelect.querySelector('.sw-multi-select__results').scrollTop += itemHeight;
            }
        },

        addItemOnEnter() {
            const activeItem = this.$refs.swMultiSelect.querySelector('.is--selected');
            const id = activeItem.dataset.id;

            if (!id) {
                return;
            }

            this.onSelectEntry(id);
        },

        deleteItemOnBackspace() {
            if (this.searchTerm.length > 0) {
                return;
            }

            const htmlList = this.$refs.swMultiSelect.getElementsByClassName('sw-multi-select__selection-item');

            if (!htmlList.length) {
                return;
            }

            const index = htmlList.length - 1;
            const list = Array.from(htmlList);
            const id = list[index].dataset.id;

            this.onDismissSelection(id);
        },

        setFocus() {
            this.$refs.swMultiSelectInput.focus();
        },

        onSelectEntry(result) {
            if (!result.id || !result.name) {
                return;
            }

            const alreadyExistsInSelection = !this.selections.every((item) => {
                return item.id !== result.id;
            });

            if (alreadyExistsInSelection) {
                return;
            }

            // Update selections array
            this.selections.push({ id: result.id, name: result.name });

            // Reset search term to reset the filtered list and collapse the drop down
            this.searchTerm = '';

            // Emit change for v-model support
            this.$emit('input', this.selections.map((item) => item.id));

            this.setFocus();
        }
    }
});
