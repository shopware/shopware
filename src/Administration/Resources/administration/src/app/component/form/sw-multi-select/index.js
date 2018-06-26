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
            entries: [],
            activePosition: 0
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
            this.serviceProvider.getList(0, 500).then((response) => {
                this.entries = response.data;
            });

            document.addEventListener('keyup', this.navigateResults);
        },

        destroyedComponent() {
            document.removeEventListener('keyup', this.navigateResults);
        },

        getCategoryEntry(id) {
            return this.entries.find((entry) => {
                return entry.id === id;
            });
        },

        onDismissEntry(id) {
            // Remove the field from the value attribute of the hidden field
            this.values = this.values.filter((entry) => entry.id !== id);

            // Emit change for v-model support
            this.$emit('input', this.values);
        },

        onSearchTermChange() {
            this.isExpanded = this.searchTerm.length > 3 && this.filteredEntries.length > 0;
        },

        openResultList() {
            this.isExpanded = true;
        },

        closeResultList() {
            this.isExpanded = false;
        },

        navigateResults(event) {
            const arrowUp = 40;
            const arrowDown = 38;

            if (!this.isExpanded) {
                return;
            }

            if (event.keyCode === arrowUp) {
                this.activePosition = this.activePosition - 1;
            } else if (event.keyCode === arrowDown) {
                this.activePosition = this.activePosition + 1;
            }
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
        }
    }
});
