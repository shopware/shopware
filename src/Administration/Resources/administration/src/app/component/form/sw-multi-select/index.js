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
        values: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
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
            activePosition: -1,
            isLoading: false
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
            return this.entries.filter((entry) => {
                const isValue = this.values.find(value => value.id === entry.id);
                return (typeof isValue !== 'undefined');
            });
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

            this.serviceProvider.getList(0, 500).then((response) => {
                this.entries = response.data;
                this.isLoading = false;
            });

            document.addEventListener('keyup', this.handleKeyboardEvents);
            document.addEventListener('click', this.closeOnClickOutside);
        },

        destroyedComponent() {
            document.removeEventListener('keyup', this.handleKeyboardEvents);
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
            this.activePosition = -1;

            this.isLoading = true;

            setTimeout(() => {
                this.isLoading = false;
            }, 1500);

            console.log(this.searchTerm);

            // TODO: Doubled request for testing!

            const params = {
                offset: 0,
                limit: 100
            };

            params.term = 'Movie';

            this.serviceProvider.getList(params).then((response) => {
                console.log(response.data);
                response.data.forEach((item) => {
                    console.log(item.name);
                });
            });
        },

        openResultList() {
            this.isExpanded = true;
        },

        closeResultList() {
            this.isExpanded = false;
        },

        handleKeyboardEvents(event) {
            const keyArrowUp = 40;
            const keyArrowDown = 38;
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

            if (event.keyCode === keyArrowUp) {
                this.activePosition = this.activePosition + 1;
            } else if (event.keyCode === keyArrowDown) {
                this.activePosition = this.activePosition - 1;
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
