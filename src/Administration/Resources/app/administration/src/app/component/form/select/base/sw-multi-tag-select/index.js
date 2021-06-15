import template from './sw-multi-tag-select.html.twig';
import './sw-multi-tag-select.scss';

const { Component, Mixin } = Shopware;
const { get } = Shopware.Utils;

/**
 * @public
 * @status ready
 * @description Renders a multi select field for data of any kind. This component uses the sw-field base
 * components. This adds the base properties such as <code>helpText</code>, <code>error</code>, <code>disabled</code> etc.
 * @example-type static
 * @component-example
 * <sw-multi-tag-select
 *     :value="['lorem', 'ipsum', 'dolor', 'sit', 'amet']"
 * ></sw-multi-tag-select>
 */
Component.register('sw-multi-tag-select', {
    template,

    inheritAttrs: false,

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: Array,
            required: true,
        },

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        validMessage: {
            type: String,
            required: false,
            default: '',
        },

        invalidMessage: {
            type: String,
            required: false,
            default: '',
        },

        validate: {
            type: Function,
            required: false,
            default: searchTerm => searchTerm.length > 0,
        },
    },

    data() {
        return {
            searchTerm: '',
            hasFocus: false,
        };
    },

    computed: {
        objectValues() {
            return this.value.map((entry) => ({ value: entry }));
        },

        errorObject() {
            return null;
        },

        inputIsValid() {
            return this.validate(this.searchTerm);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        mountedComponent() {
            this.$refs.selectionList.getFocusEl().addEventListener('keydown', this.onKeyDown);
        },

        beforeDestroyComponent() {
            this.$refs.selectionList.getFocusEl().removeEventListener('keydown', this.onKeyDown);
        },

        onKeyDown({ key }) {
            if (key.toUpperCase() === 'ENTER') {
                this.addItem();
            }
        },

        addItem() {
            this.$emit('add-item-is-valid', this.inputIsValid);

            if (!this.inputIsValid) {
                return;
            }

            this.$emit('change', [...this.value, this.searchTerm]);
            this.searchTerm = '';
        },

        remove({ value }) {
            this.$emit('change', this.value.filter(entry => entry !== value));
        },

        removeLastItem() {
            this.$emit('change', this.value.slice(0, -1));
        },

        onSearchTermChange(term) {
            this.searchTerm = term;
        },

        /* istanbul ignore next */
        getKey: get,

        setDropDown(open = true) {
            this.hasFocus = open;

            if (open) {
                return;
            }

            this.addItem();
        },
    },
});
