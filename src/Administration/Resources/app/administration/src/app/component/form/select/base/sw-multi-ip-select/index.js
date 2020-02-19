import template from './sw-multi-ip-select.html.twig';
import './sw-multi-ip-select.scss';

const { Component, Mixin } = Shopware;
const { get, string } = Shopware.Utils;

/**
 * @public
 * @deprecated tag:v6.4.0
 * @status deprecated
 * @description Renders a multi select field for IP v4 and v6 selection. This component uses the sw-field base
 * components. This adds the base properties such as <code>helpText</code>, <code>error</code>, <code>disabled</code> etc.
 * @example-type code-only
 * @component-example
 * <sw-multi-ip-select
 *     label="Multi Select"
 *     value="">
 * </sw-multi-ip-select>
 */
Component.register('sw-multi-ip-select', {
    template,

    deprecated: {
        version: '6.4.0',
        comment: [
            'This component will be replaced by a more generic implementation of',
            'a tagged multi select field "sw-multi-tag-select". The direct replacement for',
            'this component is the "sw-multi-tag-ip-select"-component.'
        ].join(' ')
    },

    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change'
    },

    mixins: [
        Mixin.getByName('remove-api-error')
    ],

    props: {
        value: {
            required: true,
            validator(value) {
                return Array.isArray(value) || value === null || value === undefined;
            }
        },

        placeholder: {
            type: String,
            required: false,
            default: ''
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            searchTerm: '',
            hasFocus: false,
            inputIsValidIp: null
        };
    },

    computed: {
        currentValue: {
            get() {
                if (!this.value) {
                    return [];
                }

                return this.value;
            },
            set(newValue) {
                this.$emit('change', newValue);
            }
        },

        objectValues() {
            const objectArray = [];

            this.currentValue.forEach((entry) => {
                objectArray.push({ value: entry });
            });

            return objectArray;
        },

        errorObject() {
            return this.inputIsValidIp === false ? { code: 'SHOPWARE_INVALID_IP' } : null;
        }
    },

    mounted() {
        this.$refs.selectionList.getFocusEl().addEventListener('keydown', this.addNewItem);
    },

    methods: {
        addNewItem({ key }) {
            key = key.toUpperCase();

            if (key !== 'ENTER') {
                return;
            }

            this.addItem();
        },

        addItem() {
            if (!this.inputIsValidIp) {
                return;
            }

            this.currentValue = [...this.currentValue, this.searchTerm];
            this.searchTerm = '';
            this.inputIsValidIp = null;
        },

        remove({ value }) {
            this.currentValue = this.currentValue.filter((entry) => {
                return entry !== value;
            });
        },

        removeLastItem() {
            if (!this.currentValue.length) {
                return;
            }

            const lastSelection = this.currentValue[this.currentValue.length - 1];
            this.remove({ value: lastSelection });
        },

        onSearchTermChange(term) {
            this.searchTerm = term;

            this.checkForValidIp();
        },

        checkForValidIp() {
            if (!this.searchTerm) {
                this.inputIsValidIp = null;
                return;
            }

            if (string.isValidIp(this.searchTerm)) {
                this.inputIsValidIp = true;
                return;
            }

            this.inputIsValidIp = false;
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },

        toggleDropDown(toggle) {
            this.hasFocus = toggle;

            if (toggle) {
                return;
            }

            this.addItem();
        }
    }
});
