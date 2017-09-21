import template from './sw-multi-select.html.twig';
import './sw-multi-select.less';

export default Shopware.ComponentFactory.register('sw-multi-select', {
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
            required: false,
            default: []
        },
        label: {
            type: String,
            default: ''
        }
    },

    data() {
        return {
            searchTerm: '',
            entries: [],
            isExpanded: false,
            selectedValues: [],
            displayValues: []
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
            return this.selectedValues.join('|');
        }
    },

    watch: {
        searchTerm: 'onSearchTermChange',
        values() {
            this.displayValues = this.values;
        }
    },

    created() {
        // Get data from the service provider
        this.serviceProvider.readAll(10, 0).then((response) => {
            this.entries = response.data;
        });
    },

    methods: {
        onDismissEntry(uuid) {
            // Remove the display item
            this.displayValues = this.displayValues.filter((entry) => entry.uuid !== uuid);

            // Remove the field from the value attribute of the hidden field
            this.selectedValues = this.selectedValues.filter((entry) => entry.uuid !== uuid);

            // Emit change for v-model support
            this.$emit('input', this.selectedValues);
        },

        onSearchTermChange() {
            this.isExpanded = this.searchTerm.length > 3 && this.filteredEntries.length > 0;
        },

        onSelectEntry(uuid, name) {
            // Update values array
            this.selectedValues.push(uuid);

            // Update display items
            this.displayValues.push({
                uuid,
                name
            });

            // Reset search term to reset the filtered list and collapse the drop down
            this.searchTerm = '';

            // Emit change for v-model support
            this.$emit('input', this.selectedValues);
        }
    },

    template
});
