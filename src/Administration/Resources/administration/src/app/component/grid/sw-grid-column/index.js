import { Component } from 'src/core/shopware';
import './sw-grid-column.less';
import template from './sw-grid-column.html.twig';

Component.register('sw-grid-column', {
    template,

    props: {
        label: {
            type: String,
            required: true
        },
        align: {
            type: String,
            default: 'left'
        },
        flex: {
            required: false,
            default: 1
        },
        sortable: {
            type: Boolean,
            required: false,
            default: false
        },
        dataIndex: {
            type: String,
            required: false,
            default: ''
        },
        editable: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    created() {
        this.registerColumn();
    },

    methods: {
        registerColumn() {
            const hasColumn = this.$parent.columns.findIndex((column) => column.label === this.label);

            if (hasColumn !== -1) {
                return;
            }

            this.$parent.columns.push({
                label: this.label,
                flex: this.flex,
                sortable: this.sortable,
                dataIndex: this.dataIndex,
                align: this.align
            });
        }
    }
});
