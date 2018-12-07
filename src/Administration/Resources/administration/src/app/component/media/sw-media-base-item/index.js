import Vue from 'vue';
import { Component } from 'src/core/shopware';
import template from './sw-media-base-item.html.twig';
import './sw-media-base-item.less';

/**
 * @status ready
 * @description The <u>sw-media-base-item</u> component is the base for items in the media manager.
 * @example-type code-only
 * @component-example
 * <sw-media-base-item
 *     isList="true"
 *     :isLoading="item.isLoading">
 * </sw-media-base-item>
 */
Component.register('sw-media-base-item', {
    template,

    inject: [
        'renameEntity',
        'rejectRenaming'
    ],

    props: {
        isList: {
            type: Boolean,
            required: false,
            default: false
        },

        showSelectionIndicator: {
            required: false,
            type: Boolean,
            default: true
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            default: true
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },

        selected: {
            type: Boolean,
            required: false,
            default: false
        },

        displayName: {
            type: String,
            required: true
        },

        editValue: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            isInlineEdit: false,
            lastContent: '',
            renamingCanceled: false
        };
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--list': this.isList,
                'is--selected': this.selected || this.isInlineEdit
            };
        },

        listSelected() {
            return this.selected && this.showSelectionIndicator;
        },

        selectionIndicatorClasses() {
            return {
                'selected-indicator--visible': this.showSelectionIndicator,
                'selected-indicator--checked': this.listSelected
            };
        }
    },

    mounted() {
        this.componentMounted();
    },

    updated() {
        this.componentUpdated();
    },

    methods: {
        componentMounted() {
            this.computeLastContent();
        },

        componentUpdated() {
            this.computeLastContent();
        },

        computeLastContent() {
            if (this.isInlineEdit) {
                return;
            }

            const el = this.$refs.itemName;
            if (el.offsetWidth < el.scrollWidth) {
                this.lastContent = this.displayName.slice(-3);
                return;
            }

            this.lastContent = '';
        },

        handleItemClick(originalDomEvent) {
            if (this.isSelectionIndicatorClicked(originalDomEvent.composedPath())) {
                return;
            }

            this.$emit('sw-media-item-clicked', originalDomEvent);
        },

        isSelectionIndicatorClicked(path) {
            return path.some((parent) => {
                return parent.classList && (
                    parent.classList.contains('sw-media-base-item__selected-indicator') ||
                    parent.classList.contains('sw-context-button')
                );
            });
        },

        onClickedItem(originalDomEvent) {
            if (!this.listSelected) {
                this.selectItem(originalDomEvent);
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        selectItem(originalDomEvent) {
            this.$emit('sw-media-item-selection-add', originalDomEvent);
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('sw-media-item-selection-remove', originalDomEvent);
        },

        startInlineEdit() {
            this.isInlineEdit = true;
        },

        endInlineEdit() {
            this.isInlineEdit = false;
        },

        onCancelRenaming() {
            this.renamingCanceled = true;
            this.endInlineEdit();
            Vue.nextTick(() => {
                this.rejectRenaming('canceled');
            });
        },

        onDoRenaming() {
            this.renamingCanceled = false;
            this.endInlineEdit();
        },

        onBlurInlineEdit() {
            if (this.renamingCanceled === true) {
                return;
            }

            const inputField = this.$refs.inputItemName;
            if (!inputField.currentValue || !inputField.currentValue.trim()) {
                this.endInlineEdit();
                Vue.nextTick(() => {
                    this.rejectRenaming('empty-name');
                });
                return;
            }

            this.renameEntity(inputField.currentValue).finally(() => {
                this.endInlineEdit();
            });
        }
    }
});
