import './sw-grid-column.scss';
import template from './sw-grid-column.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-grid-column', {
    template,

    inject: ['feature'],

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
            let index = -1;
            if (this.feature.isActive('VUE3')) {
                index = this.$parent.$parent.$parent.columns.findIndex((col) => col.label === oldLabel);
            } else {
                index = this.$parent.columns.findIndex((col) => col.label === oldLabel);
            }

            if (index === -1 || !newLabel) {
                return;
            }

            if (this.feature.isActive('VUE3')) {
                this.$parent.$parent.$parent.columns[index].label = newLabel;
                return;
            }

            this.$parent.columns[index].label = newLabel;
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
            let hasColumn = false;
            if (this.feature.isActive('VUE3')) {
                hasColumn = this.$parent.$parent.$parent.$parent.columns.findIndex((column) => column.label === this.label);
            } else {
                hasColumn = this.$parent.columns.findIndex((col) => col.label === this.label);
            }

            if (hasColumn !== -1 && this.label) {
                return;
            }

            if (this.feature.isActive('VUE3')) {
                this.$parent.$parent.$parent.$parent.columns.push({
                    label: this.label,
                    iconLabel: this.iconLabel,
                    flex: this.flex,
                    sortable: this.sortable,
                    dataIndex: this.dataIndex,
                    align: this.align,
                    editable: this.editable,
                    truncate: this.truncate,
                });
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
