import { Component } from 'src/core/shopware';
import template from './sw-media-base-item.html.twig';
import './sw-media-base-item.less';

/**
 * @private
 */
Component.register('sw-media-base-item', {
    template,

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
        }
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--list': this.isList
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

    methods: {
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
        }
    }
});
