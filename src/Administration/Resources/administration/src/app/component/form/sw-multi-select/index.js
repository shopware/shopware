import './sw-multi-select.less';
import template from './sw-multi-select.html.twig';

Shopware.Component.register('sw-multi-select', {
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
            entries: []
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

        stringifyValues() {
            return this.values.join('|');
        }
    },

    watch: {
        searchTerm: 'onSearchTermChange'
    },

    created() {
        // Get data from the service provider
        this.serviceProvider.getList(100, 0).then((response) => {
            this.entries = response.data;
        });
    },

    methods: {
        onDismissEntry(id) {
            // Remove the field from the value attribute of the hidden field
            this.values = this.values.filter((entry) => entry.id !== id);

            // Emit change for v-model support
            this.$emit('input', this.values);
        },

        onSearchTermChange() {
            this.isExpanded = this.searchTerm.length > 3 && this.filteredEntries.length > 0;
        },

        onSelectEntry(id) {
            if (!id) {
                return false;
            }

            const selectedEntry = this.entries.find((item) => {
                return item.id === id;
            });

            if (!selectedEntry) {
                return false;
            }

            // Update values array
            this.values.push(selectedEntry);

            // Reset search term to reset the filtered list and collapse the drop down
            this.searchTerm = '';

            // Emit change for v-model support
            this.$emit('input', this.values);

            return selectedEntry;
        }
    },

    template
});
