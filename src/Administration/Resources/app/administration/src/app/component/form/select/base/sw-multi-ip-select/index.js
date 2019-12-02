import template from './sw-multi-ip-select.html.twig';
import './sw-multi-ip-select.scss';

const { Component } = Shopware;
const { get } = Shopware.Utils;

/**
 * @public
 * @status ready
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

    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change'
    },

    props: {
        value: {
            type: [Array, Object],
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
            searchTerm: ''
        };
    },

    computed: {
        objectValues() {
            const objectArray = [];

            this.value.forEach((entry) => {
                objectArray.push({ value: entry });
            });

            return objectArray;
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

            // https://regex101.com/r/qHTUIe/1
            // eslint-disable-next-line max-len
            if (!RegExp('((^\\s*((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))\\s*$)|(^\\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:)))(%.+)?\\s*$))').test(this.searchTerm)) {
                return;
            }

            const newValue = [...this.value, this.searchTerm];
            this.$emit('change', newValue);
            this.searchTerm = '';
        },

        remove({ value }) {
            this.$emit('change', this.value.filter((entry) => {
                return entry !== value;
            }));
        },

        removeLastItem() {
            if (!this.value.length) {
                return;
            }

            const lastSelection = this.value[this.value.length - 1];
            this.remove({ value: lastSelection });
        },

        onSearchTermChange(term) {
            this.searchTerm = term;
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        }
    }
});
