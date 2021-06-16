import './sw-grid-column.scss';
import template from './sw-grid-column.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-grid-column', {
    template,

    props: {
        label: {
            type: String,
            required: false,
            default: null,
        },
        iconLabel: {
            type: String,
            required: false,
            default: null,
        },
        align: {
            type: String,
            default: 'left',
        },
        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        flex: {
            required: false,
            default: 1,
        },
        sortable: {
            type: Boolean,
            required: false,
            default: false,
        },
        dataIndex: {
            type: String,
            required: false,
            default: '',
        },
        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
        truncate: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    watch: {
        label(newLabel, oldLabel) {
            const index = this.$parent.columns.findIndex((col) => col.label === oldLabel);

            if (index !== -1 && newLabel) {
                this.$parent.columns[index].label = newLabel;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.registerColumn();
        },

        registerColumn() {
            const hasColumn = this.$parent.columns.findIndex((column) => column.label === this.label);

            if (hasColumn !== -1 && this.label) {
                return;
            }

            this.$parent.columns.push({
                label: this.label,
                iconLabel: this.iconLabel,
                flex: this.flex,
                sortable: this.sortable,
                dataIndex: this.dataIndex,
                align: this.align,
                editable: this.editable,
                truncate: this.truncate,
            });
        },
    },
});
