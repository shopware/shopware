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
        // values: {
        //     type: Array,
        //     required: true,
        //     default() {
        //         return [];
        //     }
        // },
        label: {
            type: String,
            default: ''
        },
        id: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,

            // Search results entries
            entries: [],

            // Selected values
            values: [],

            activePosition: 0,
            isLoading: false,
            timeout: null
        };
    },

    computed: {
        // Client side filtered
        filteredEntries() {
            const searchTerm = this.searchTerm.toLowerCase();

            return this.entries.filter((entry) => {
                const entryName = entry.name.toLowerCase();
                return entryName.indexOf(searchTerm) !== -1;
            });
        },

        displayValues() {
            // TODO: Replace filtering of search result entries
            return this.entries.filter((entry) => {
                const isValue = this.values.find(value => value.id === entry.id);
                return (typeof isValue !== 'undefined');
            });

            // TODO: Get category names by IDs in case a category name changes
            // return this.values;
        },

        stringifyValues() {
            return this.values.join('|');
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
            this.isLoading = true;

            this.serviceProvider.getList(0, 15).then((response) => {
                this.entries = response.data;
                this.isLoading = false;
            });

            document.addEventListener('keyup', this.handleKeyUpActions);
            document.addEventListener('keydown', this.handleKeyDownActions);
            document.addEventListener('click', this.closeOnClickOutside);
        },

        destroyedComponent() {
            document.removeEventListener('keyup', this.handleKeyUpActions);
            document.removeEventListener('keydown', this.handleKeyDownActions);
            document.removeEventListener('click', this.closeOnClickOutside);
        },

        closeOnClickOutside(event) {
            const target = event.target;

            if (target.closest('.sw-multi-select') === null) {
                this.isExpanded = false;
                this.activePosition = 0;
            }
        },

        getCategoryEntry(id) {
            return this.entries.find((entry) => {
                return entry.id === id;
            });
        },

        onDismissEntry(id) {
            if (!id) {
                return;
            }

            // Remove the field from the value attribute of the hidden field
            this.values = this.values.filter((entry) => entry.id !== id);

            // Emit change for v-model support
            this.$emit('input', this.values);

            this.setFocus();
        },

        onSearchTermChange() {
            this.activePosition = 0;
            this.isLoading = true;

            clearTimeout(this.timeout);

            this.timeout = setTimeout(() => {
                this.serviceProvider.getList(0, 200, { term: this.searchTerm }).then((response) => {
                    this.entries = response.data;
                    this.isLoading = false;
                });
            }, 400);
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

            if (event.keyCode === keyArrowUp) {
                this.navigateUpSearchResults();
            } else if (event.keyCode === keyArrowDown) {
                this.navigateDownSearchResults();
            }
        },

        navigateUpSearchResults() {
            if (this.activePosition === 0) {
                return;
            }
            this.activePosition = this.activePosition - 1;
            const itemHeight = this.$refs.swMultiSelect.querySelector('.sw-multi-select__results-entry').offsetHeight;
            this.$refs.swMultiSelect.querySelector('.sw-multi-select__results').scrollTop -= itemHeight;
        },

        navigateDownSearchResults() {
            if (this.activePosition === this.filteredEntries.length - 1) {
                return;
            }

            this.activePosition = this.activePosition + 1;

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

            const htmlList = this.$refs.swMultiSelect.getElementsByClassName('sw-multi-select__list-item');

            if (!htmlList.length) {
                return;
            }

            const index = htmlList.length - 1;
            const list = Array.from(htmlList);
            const id = list[index].dataset.id;

            this.onDismissEntry(id);
        },

        setFocus() {
            this.$refs.swMultiSelectInput.focus();
        },

        onSelectEntry(id) {
            if (!id) {
                return;
            }

            // Update values array
            this.values.push({ id });

            // Reset search term to reset the filtered list and collapse the drop down
            this.searchTerm = '';

            // Emit change for v-model support
            this.$emit('input', this.values);

            this.setFocus();
        }
    }
});
