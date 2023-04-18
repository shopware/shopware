/**
 * @package admin
 */

import './sw-meteor-single-select.scss';
import template from './sw-meteor-single-select.html.twig';

const { Component, Mixin } = Shopware;
const { debounce, get } = Shopware.Utils;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-meteor-single-select', {
    template,

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        options: {
            required: true,
            type: Array,
        },

        // FIXME: add type for property
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        highlightSearchTerm: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        labelProperty: {
            type: String,
            required: false,
            default: 'label',
        },

        valueProperty: {
            type: String,
            required: false,
            default: 'value',
        },
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            results: this.options,
            // used to track if an item was selected before closing the result list
            itemRecentlySelected: false,
        };
    },

    computed: {
        currentValue: {
            get() {
                return this.value;
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
                return this.options.find(option => {
                    return this.getKey(option, this.valueProperty) === this.currentValue;
                });
            },
            set(newValue) {
                this.currentValue = this.getKey(newValue, this.valueProperty);
            },
        },

        selectedValueLabel() {
            if (!this.singleSelection) {
                return this.placeholder;
            }

            return this.getKey(this.singleSelection, this.labelProperty);
        },

        searchable() {
            return this.options.length >= 7;
        },
    },

    methods: {
        isSelected(item) {
            return this.getKey(item, this.valueProperty) === this.value;
        },

        toggleResultList() {
            if (this.isExpanded) {
                this.closeResultList();
            } else {
                this.openResultList();
            }
        },

        openResultList() {
            // Always start with a fresh list when opening the result list
            this.results = this.options;
            this.isExpanded = true;
        },

        closeResultList() {
            this.isExpanded = false;
            this.searchTerm = '';
        },

        setValue(item) {
            this.itemRecentlySelected = true;
            this.singleSelection = item;
            this.closeResultList();
        },

        onInputSearchTerm() {
            this.debouncedSearch();
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.search();
        }, 100),

        search() {
            this.$emit('search', this.searchTerm);

            this.results = this.options.filter(option => {
                const label = this.getKey(option, this.labelProperty);
                if (!label) {
                    return false;
                }
                return label.toLowerCase().includes(this.searchTerm.toLowerCase());
            });
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },
    },
});
