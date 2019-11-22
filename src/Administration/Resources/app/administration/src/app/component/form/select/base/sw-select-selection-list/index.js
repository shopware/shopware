import template from './sw-select-selection-list.html.twig';
import './sw-select-selection-list.scss';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @description Base component for rendering selection lists.
 * @example-type code-only
 */
Component.register('sw-select-selection-list', {
    template,

    props: {
        selections: {
            type: Array,
            required: false,
            default: []
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
        enableSearch: {
            type: Boolean,
            required: false,
            default: true
        },
        invisibleCount: {
            type: Number,
            required: false,
            default: 0
        },
        size: {
            type: String,
            required: false
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
        },
        searchTerm: {
            type: String,
            required: false,
            default: ''
        }
    },

    methods: {
        onClickInvisibleCount() {
            this.$emit('total-count-click');
        },

        onSearchTermChange(event) {
            this.$emit('search-term-change', event.target.value, event);
        },

        onKeyDownDelete() {
            if (this.searchTerm.length < 1) {
                this.$emit('last-item-delete');
            }
        },

        onClickDismiss(item) {
            this.$emit('item-remove', item);
        },

        focus() {
            this.$refs.swSelectInput.focus();
        },

        blur() {
            this.$refs.swSelectInput.blur();
        },

        select() {
            this.$refs.swSelectInput.select();
        },

        getFocusEl() {
            return this.$refs.swSelectInput;
        }
    }
});
