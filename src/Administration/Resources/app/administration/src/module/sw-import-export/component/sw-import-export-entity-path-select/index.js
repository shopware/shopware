import './sw-import-export-entity-path-select.scss';
import template from './sw-import-export-entity-path-select.html.twig';

const { Component, Mixin } = Shopware;
const { debounce, get, flow } = Shopware.Utils;

/**
 * @private
 */
Component.register('sw-import-export-entity-path-select', {
    template,

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        // FIXME: add type attribute
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },
        entityType: {
            type: String,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true,
        },
        placeholder: {
            type: String,
            required: false,
            default: '',
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'value',
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
            },
        },

        currencies: {
            type: Array,
            required: false,
            default() {
                return [{ isoCode: 'DEFAULT' }];
            },
        },

        languages: {
            type: Array,
            required: false,
            default() {
                return [{ locale: 'DEFAULT' }];
            },
        },
    },

    data() {
        return {
            labelProperty: 'label',
            searchInput: '',
            actualSearch: '',
            isExpanded: false,
            // used to track if an item was selected before closing the result list
            itemRecentlySelected: false,
            priceProperties: ['net', 'gross', 'currencyId', 'linked', 'listPrice'],
            visibilityProperties: ['all', 'link', 'search'],
        };
    },

    computed: {
        currentValue: {
            get() {
                return this.value || '';
            },
            set(newValue) {
                this.$emit('change', newValue);
            },
        },

        inputClasses() {
            return {
                'is--expanded': this.isExpanded,
            };
        },

        selectionTextClasses() {
            return {
                'is--placeholder': !this.singleSelection,
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
            },
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
            const pathParts = (this.isExpanded && this.actualSearch) ?
                this.actualSearch.split('.') : this.currentValue.split('.');

            // remove last element of path which is the user search input
            pathParts.splice(-1, 1);

            // Remove special cases for prices and translations
            return pathParts.filter(part => {
                // Remove if path is a iso code
                if (this.availableIsoCodes.includes(part)) {
                    return false;
                }
                // Remove if path is a locale code
                if (this.availableLocales.includes(part)) {
                    return false;
                }

                return !(
                    part === 'translations' ||
                    part === 'visibilities' ||
                    part === 'price' ||
                    part === 'purchasePrices'
                );
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
                const property = actualDefinition.properties[propertyName];

                // Return if propertyName does not exist in the definition, e.g. "DEFAULT", "en_GB"
                if (!property) {
                    return;
                }

                // Return if property is translations association
                if (propertyName === 'translations' && property.relation === 'one_to_many') {
                    return;
                }

                // Return if property is a visibility association
                if (propertyName === 'visibilities' && property.relation === 'one_to_many') {
                    return;
                }

                // Return if property is a assignedProducts association
                if (propertyName === 'assignedProducts' && property.relation === 'one_to_many') {
                    return;
                }

                // Return if property is a price
                if (propertyName === 'price' && property.type === 'json_object') {
                    return;
                }

                const entity = actualDefinition.properties[propertyName].entity;
                if (Shopware.EntityDefinition.has(entity)) {
                    actualDefinition = Shopware.EntityDefinition.get(entity);
                }
            });

            return actualDefinition.entity;
        },

        processFunctions() {
            return [this.processTranslations, this.processVisibilities, this.processAssignedProducts, this.processPrice, this.processProperties];
        },

        options() {
            const definition = Shopware.EntityDefinition.get(this.currentEntity);
            const unprocessedValues = {
                definition: definition,
                options: [],
                properties: Object.keys(definition.properties),
                path: this.actualPathPrefix.length > 0 ? this.actualPathPrefix.replace(/\.?$/, '.') : this.actualPathPrefix,
            };

            // flow is from lodash
            const { options } = flow(this.processFunctions)(unprocessedValues);

            return options.sort(this.sortOptions);
        },

        results() {
            return this.searchFunction(
                {
                    options: this.options,
                    labelProperty: this.labelProperty,
                    valueProperty: this.valueProperty,
                    searchTerm: this.searchTerm,
                },
            );
        },

        availableIsoCodes() {
            return this.currencies.map(currency => currency.isoCode);
        },

        availableLocales() {
            return this.languages.map(language => language.locale.code);
        },

        searchTerm() {
            return this.actualSearch.split('.').pop();
        },
    },

    methods: {
        isSelected(item) {
            return this.getKey(item, this.valueProperty) === this.value;
        },

        onSelectExpanded() {
            this.isExpanded = true;

            // Get the search text of the selected item as prefilled value
            this.searchInput = this.currentValue;

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
            if (this.searchInput === '' && !this.itemRecentlySelected) {
                this.$emit('before-selection-clear', this.singleSelection, this.value);
                this.currentValue = null;
            }

            this.$refs.swSelectInput.blur();
            this.searchInput = '';
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
                this.actualSearch = `${item.value}.`;
                this.searchInput = this.actualSearch;
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

        onInputSearch() {
            this.debouncedSearch();
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.search();
        }, 300),

        search() {
            this.$emit('search', this.searchInput);

            this.actualSearch = this.searchInput;

            this.$nextTick(() => {
                this.resetActiveItem();
            });
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },

        processTranslations({ definition, options, properties, path }) {
            const translationProperty = definition.properties.translations;

            if (!translationProperty || translationProperty.relation !== 'one_to_many') {
                return { properties, options, definition, path };
            }

            const translationDefinition = Shopware.EntityDefinition.get(translationProperty.entity);
            const translationProperties = Object.keys(translationDefinition.properties);

            const newOptions = [...options, ...this.getTranslationProperties(path, translationProperties)];

            // Remove translation property and translatable properties
            const filteredProperties = properties.filter(propertyName => {
                return !translationProperties.includes(propertyName) && propertyName !== 'translations';
            });

            return {
                properties: filteredProperties,
                options: newOptions,
                definition: definition,
                path: path,
            };
        },

        getTranslationProperties(path, properties) {
            path = `${path}translations.`;
            const options = [];

            this.availableLocales.forEach((locale) => {
                properties.forEach(propertyName => {
                    const name = `${path}${locale}.${propertyName}`;
                    options.push({ label: name, value: name });
                });
            });

            return options;
        },

        processPrice({ definition, options, properties, path }) {
            const priceProperty = definition.properties.price;

            if (!priceProperty || priceProperty.type !== 'json_object') {
                return { properties, options, definition, path };
            }

            const newOptions = [...options, ...this.getPriceProperties(path)];

            // Remove visibility property
            const filteredProperties = properties.filter(propertyName => {
                return propertyName !== 'price' && propertyName !== 'purchasePrices';
            });

            return {
                properties: filteredProperties,
                options: newOptions,
                definition: definition,
                path: path,
            };
        },

        getPriceProperties(path) {
            return [
                ...this.generatePriceProperties('price', path),
                ...this.generatePriceProperties('purchasePrices', path),
            ];
        },

        generatePriceProperties(priceType, path) {
            const options = [];

            this.currencies.forEach((currency) => {
                this.priceProperties.forEach(propertyName => {
                    const name = `${path}${priceType}.${currency.isoCode}.${propertyName}`;
                    options.push({ label: name, value: name });
                });
            });

            return options;
        },

        processProperties({ definition, options, properties, path }) {
            const newOptions = [...options];

            properties.forEach((propertyName) => {
                const name = `${path}${propertyName}`;
                const property = definition.properties[propertyName];

                if (property.relation === 'one_to_many') {
                    return;
                }

                newOptions.push({ label: name, value: name, relation: property.relation });
            });

            return { definition, options: newOptions, properties, path };
        },

        processVisibilities({ definition, options, properties, path }) {
            const visibilityProperty = definition.properties.visibilities;

            if (!visibilityProperty || visibilityProperty.relation !== 'one_to_many') {
                return { properties, options, definition, path };
            }

            const newOptions = [...options, ...this.getVisibilityProperties(path)];

            // Remove visibility property
            const filteredProperties = properties.filter(propertyName => {
                return propertyName !== 'visibilities';
            });

            return {
                properties: filteredProperties,
                options: newOptions,
                definition: definition,
                path: path,
            };
        },

        getVisibilityProperties(path) {
            const options = [];

            this.visibilityProperties.forEach(property => {
                const name = `${path}visibilities.${property}`;
                options.push({ label: name, value: name });
            });

            return options;
        },

        processAssignedProducts({ definition, options, properties, path }) {
            const assignedProductsProperty = definition.properties.assignedProducts;

            if (!assignedProductsProperty || assignedProductsProperty.relation !== 'one_to_many') {
                return { properties, options, definition, path };
            }

            const newOptions = [...options, ...this.getAssignedProductsProperties(path)];

            // Remove assignedProducts property
            const filteredProperties = properties.filter(propertyName => {
                return propertyName !== 'assignedProducts';
            });

            return {
                properties: filteredProperties,
                options: newOptions,
                definition: definition,
                path: path,
            };
        },

        getAssignedProductsProperties(path) {
            const name = `${path}assignedProducts`;

            return [{ label: name, value: name }];
        },

        sortOptions(a, b) {
            if (a.value > b.value) {
                return 1;
            }
            if (b.value > a.value) {
                return -1;
            }
            return 0;
        },
    },
});
