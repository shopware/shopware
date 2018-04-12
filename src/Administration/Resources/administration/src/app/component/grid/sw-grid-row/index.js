import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-grid-row.html.twig';
import './sw-grid-row.less';

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

        this.$parent.$on('sw-grid-disable-inline-editing', () => {
            this.onInlineEditCancel();
        });
    },

    methods: {
        onInlineEditStart() {
            // Inline editing is enabled, we don't have to re-enable it again
            if (this.isEditingActive) {
                return;
            }

            this.isEditingActive = true;
            this.$parent.$emit('sw-row-inline-edit-start', this.id);
        },

        onInlineEditCancel() {
            this.isEditingActive = false;
            this.$parent.$emit('sw-row-inline-edit-cancel', this.id);
        },

        onInlineEditFinish() {
            this.isEditingActive = false;
            this.$parent.$emit('inline-edit-finish', {
                id: this.id,
                item: this.item
            });
        }
    }
});
