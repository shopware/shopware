import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-grid-row.html.twig';
import './sw-grid-row.less';

/**
 * @private
 */
Component.register('sw-grid-row', {
    template,

    props: {
        item: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            columns: [],
            isEditingActive: false,
            inlineEditingCls: 'is--inline-editing',
            id: utils.createId()
        };
    },

    watch: {
        isEditingActive() {
            if (this.isEditingActive) {
                this.$refs.swGridRow.classList.add(this.inlineEditingCls);
                return;
            }

            this.$refs.swGridRow.classList.remove(this.inlineEditingCls);
        }
    },

    created() {
        // Bubble up columns declaration for the column header definition
        this.$parent.columns = this.columns;

        this.$parent.$on('sw-grid-disable-inline-editing', (id) => {
            this.onInlineEditCancel(id);
        });
    },

    methods: {
        onInlineEditStart() {
            if (this.$device.getViewportWidth() < 800) {
                return;
            }

            let isInlineEditingConfigured = false;

            // If inline editing is already enabled, or no column has
            // the property "editable" we don't have to enable it.
            this.columns.forEach((column) => {
                if (column.editable || isInlineEditingConfigured) {
                    isInlineEditingConfigured = true;
                }
            });

            if (this.isEditingActive || !isInlineEditingConfigured) {
                return;
            }

            this.isEditingActive = true;
            this.$parent.$emit('sw-row-inline-edit-start', this.id);
        },

        onInlineEditCancel(id) {
            if (id && id !== this.id) {
                return;
            }

            this.isEditingActive = false;
            this.$parent.$emit('sw-row-inline-edit-cancel', this.id);
            this.$parent.$emit('inline-edit-cancel', this.item);
        },

        onInlineEditFinish() {
            this.isEditingActive = false;
            this.$parent.$emit('inline-edit-finish', this.item);
        }
    }
});
