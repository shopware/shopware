import './sw-import-export-entity-path-select.scss';
import template from './sw-import-export-entity-path-select.html.twig';

const { Component, Mixin } = Shopware;
const { debounce, get } = Shopware.Utils;

/**
 * @private
 */
Component.register('sw-import-export-entity-path-select', {
    template,

    model: {
        prop: 'value',
        event: 'change'
    },

    mixins: [
        Mixin.getByName('remove-api-error')
    ],

    props: {
        value: {
            required: true
        },
        entityType: {
            type: String,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },
        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        labelProperty: {
            type: String,
            required: false,
            default: 'label'
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'value'
        },

        searchFunction: {
            type: Function,
            required: false,
            default({ options, labelProperty, searchTerm }) {
                return options.filter(option => {
                    const label = this.getKey(option, labelProperty);
                    if (!label) {
                        return false;
                    }
                    return label.toLowerCase().includes(searchTerm.toLowerCase());
                });
            }
        },

        currencies: {
            type: Array,
            required: false,
            default() {
                return [{ isoCode: 'DEFAULT' }];
            }
        },

        languages: {
            type: Array,
            required: false,
            default() {
                return [{ locale: 'DEFAULT' }];
            }
        }
    },

    data() {
        return {
            searchTerm: '',
            actualSearch: '',
            isExpanded: false,
            // used to track if an item was selected before closing the result list
            itemRecentlySelected: false,
            priceProperties: ['net', 'gross', 'currencyId', 'linked', 'listPrice'],
            visibilityProperties: ['all', 'link', 'search'],
            allowedOneToManyAssociations: ['visibilities', 'translations']
        };
    },

    computed: {
        currentValue: {
            get() {
                return this.value || '';
            },
            set(newValue) {
                this.$emit('change', newValue);
            }
        },

        inputClasses() {
            return {
                'is--expanded': this.isExpanded
            };
        },

        selectionTextClasses() {
            return {
                'is--placeholder': !this.singleSelection
            };
        },

        singleSelection: {
            get() {
                return this.results.find(option => {
                    return this.getKey(option, this.valueProperty) === this.currentValue;
                });
            },
            set(newValue) {
                this.currentValue = this.getKey(newValue, this.valueProperty);
            }
        },

        /**
         * Returns the visibleResults with the actual selection as first entry
         * @returns {Array}
         */
        visibleResults() {
            if (this.singleSelection) {
                const results = [];
                results.push(this.singleSelection);
                const value = this.getKey(this.singleSelection, this.valueProperty);
                this.results.forEach(option => {
                    // Prevent duplicate options
                    if (this.getKey(option, this.valueProperty) !== value) {
                        results.push(option);
                    }
                });
                return results;
            }

            return this.results;
        },

        actualPathPrefix() {
            return this.actualPathParts.length > 0 ? this.actualPathParts.join('.') : '';
        },

        actualPathParts() {
            const pathParts = this.isExpanded ? this.searchTerm.split('.') : this.currentValue.split('.');

            // remove last element of path which is the user search input
            pathParts.splice(-1, 1);

            // Remove special cases for prices and translations
            return pathParts.filter(part => {
                return !(this.availableIsoCodes.includes(part) || this.availableLocales.includes(part));
            });
        },

        currentEntity() {
            if (this.actualPathParts.length < 1) {
                return this.entityType;
            }

            const pathParts = this.actualPathParts;

            // Use this.entityType if there is not path yet
            if (pathParts.length === 0) {
                return this.entityType;
            }

            let actualDefinition = Shopware.EntityDefinition.get(this.entityType);

            pathParts.forEach((propertyName) => {
                // Return if propertyName does not exist in the definition, e.g. "DEFAULT", "en_GB"
                if (!actualDefinition.properties[propertyName]) {
                    return;
                }
                const entity = actualDefinition.properties[propertyName].entity;
                if (Shopware.EntityDefinition.has(entity)) {
                    actualDefinition = Shopware.EntityDefinition.get(entity);
                }
            });

            // Special case for prices
            if (pathParts[pathParts.length - 1] === 'price' && actualDefinition.properties.price.type === 'json_object') {
                return 'price';
            }

            return actualDefinition.entity;
        },

        options() {
            let path = this.actualPathPrefix;
            if (path.length > 0) {
                path = path.replace(/\.?$/, '.');
            }
            // Special case for prices
            if (this.currentEntity === 'price') {
                return this.getPriceProperties(path);
            }

            // Special case for visibility
            if (this.currentEntity === 'product_visibility') {
                return this.getVisibilityProperties(path);
            }

            const definition = Shopware.EntityDefinition.get(this.currentEntity);
            const properties = Object.keys(definition.properties);

            // Special case for translations
            if (this.actualPathParts[this.actualPathParts.length - 1] === 'translations') {
                return this.getTranslationProperties(this.currentEntity, path, properties);
            }

            const options = [];
            properties.forEach((propertyName) => {
                const name = `${path}${propertyName}`;
                const property = definition.properties[propertyName];

                // Special case if property is a price property
                if (propertyName === 'price' && property.type === 'json_object') {
                    options.push({ label: name, value: name, relation: 'price' });
                    return;
                }

                // Skip all not allowed one to many associations
                if (property.relation === 'one_to_many' && !this.allowedOneToManyAssociations.includes(propertyName)) {
                    return;
                }
                options.push({ label: name, value: name, relation: property.relation });
            });

            return options;
        },

        results() {
            return this.searchFunction(
                {
                    options: this.options,
                    labelProperty: this.labelProperty,
                    valueProperty: this.valueProperty,
                    searchTerm: this.actualSearch
                }
            );
        },

        availableIsoCodes() {
            return this.currencies.map(currency => currency.isoCode);
        },

        availableLocales() {
            return this.languages.map(language => language.locale.code);
        }
    },

    methods: {
        isSelected(item) {
            return this.getKey(item, this.valueProperty) === this.value;
        },

        onSelectExpanded() {
            this.isExpanded = true;

            // Get the search text of the selected item as prefilled value
            this.searchTerm = this.currentValue;

            this.$nextTick(() => {
                this.resetActiveItem();
                this.$refs.swSelectInput.select();
                this.$refs.swSelectInput.focus();
            });
        },

        tryGetSearchText(option) {
            return this.getKey(option, this.labelProperty, '');
        },

        onSelectCollapsed() {
            // Empty the selection if the search term is empty
            if (this.searchTerm === '' && !this.itemRecentlySelected) {
                this.$emit('before-selection-clear', this.singleSelection, this.value);
                this.currentValue = null;
            }

            this.$refs.swSelectInput.blur();
            this.searchTerm = '';
            this.actualSearch = '';
            this.itemRecentlySelected = false;
            this.isExpanded = false;
        },

        closeResultList() {
            this.$refs.selectBase.collapse();
        },

        setValue(item) {
            this.itemRecentlySelected = true;
            this.singleSelection = item;

            // If selected item is a relation
            if (item.relation && item.relation !== 'many_to_many') {
                this.searchTerm = `${item.value}.`;
                this.$refs.swSelectInput.select();
                return;
            }

            this.currentValue = item.value;

            this.closeResultList();
        },

        resetActiveItem(pos = 0) {
            // Return if the result list is closed before the search request returns
            if (!this.$refs.resultsList) {
                return;
            }
            // If an item is selected the second entry is the first search result
            if (this.singleSelection) {
                pos = 1;
            }
            this.$refs.resultsList.setActiveItemIndex(pos);
        },

        onInputSearchTerm() {
            this.debouncedSearch();
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.search();
        }, 100),

        search() {
            this.$emit('search', this.searchTerm);

            this.actualSearch = this.searchTerm;

            this.$nextTick(() => {
                this.resetActiveItem();
            });
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },

        getTranslationProperties(entity, path, properties) {
            const options = [];

            this.availableLocales.forEach((locale) => {
                properties.forEach(propertyName => {
                    const name = `${path}${locale}.${propertyName}`;
                    options.push({ label: name, value: name });
                });
            });

            return options;
        },

        getPriceProperties(path) {
            const options = [];

            this.currencies.forEach((currency) => {
                this.priceProperties.forEach(propertyName => {
                    const name = `${path}${currency.isoCode}.${propertyName}`;
                    options.push({ label: name, value: name });
                });
            });

            return options;
        },

        getVisibilityProperties(path) {
            const options = [];

            this.visibilityProperties.forEach(property => {
                const name = `${path}${property}`;
                options.push({ label: name, value: name });
            });

            return options;
        }
    }
});
