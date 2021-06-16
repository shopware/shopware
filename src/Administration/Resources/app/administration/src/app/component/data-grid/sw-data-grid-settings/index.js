import template from './sw-data-grid-settings.html.twig';
import './sw-data-grid-settings.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-data-grid-settings', {
    template,

    props: {
        columns: {
            type: Array,
            default() {
                return [];
            },
            required: true,
        },
        compact: {
            type: Boolean,
            required: true,
            default: false,
        },
        previews: {
            type: Boolean,
            required: true,
            default: false,
        },
        enablePreviews: {
            type: Boolean,
            required: true,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    data() {
        return {
            currentCompact: this.compact,
            currentPreviews: this.previews,
            currentColumns: this.columns,
        };
    },

    computed: {
        contextMenuClasses() {
            return {
                'sw-data-grid-settings': true,
            };
        },
    },

    watch: {
        columns() {
            this.currentColumns = this.columns;
        },

        compact() {
            this.currentCompact = this.compact;
        },

        previews() {
            this.currentPreviews = this.previews;
        },
    },

    methods: {
        onChangeCompactMode(value) {
            this.$emit('change-compact-mode', value);
        },

        onChangePreviews(value) {
            this.$emit('change-preview-images', value);
        },

        onChangeColumnVisibility(value, index) {
            this.$emit('change-column-visibility', value, index);
        },

        onClickChangeColumnOrderUp(columnIndex) {
            this.$emit('change-column-order', columnIndex, columnIndex - 1);
        },

        onClickChangeColumnOrderDown(columnIndex) {
            this.$emit('change-column-order', columnIndex, columnIndex + 1);
        },
    },
});
