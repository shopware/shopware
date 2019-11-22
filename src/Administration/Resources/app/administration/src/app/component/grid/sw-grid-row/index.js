import template from './sw-grid-row.html.twig';
import './sw-grid-row.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @private
 */
Component.register('sw-grid-row', {
    template,

    props: {
        item: {
            type: Object,
            required: true
        },
        index: {
            type: Number,
            required: false
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
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // Bubble up columns declaration for the column header definition
            this.$parent.columns = this.columns;

            this.$parent.$on('sw-grid-disable-inline-editing', (id) => {
                this.onInlineEditCancel(id);
            });
        },

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
            this.$parent.$emit('inline-edit-start', this.item);
        },

        onInlineEditCancel(id, index) {
            if (id && id !== this.id) {
                return;
            }

            this.isEditingActive = false;
            this.$parent.$emit('sw-row-inline-edit-cancel', this.id, index);
            this.$parent.$emit('inline-edit-cancel', this.item, index);
        },

        onInlineEditFinish() {
            this.isEditingActive = false;
            this.$emit('inline-edit-finish', this.item);
        }
    }
});
