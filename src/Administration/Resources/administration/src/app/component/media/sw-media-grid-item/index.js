import { Component } from 'src/core/shopware';
import template from './sw-media-grid-item.html.twig';
import './sw-media-grid-item.less';

Component.register('sw-media-grid-item', {
    template,

    props: {
        selected: {
            type: Boolean,
            required: true
        },

        item: {
            required: true,
            type: Object
        },

        containerOptions: {
            required: true,
            type: Object
        },

        showContextMenuButton: {
            required: false,
            type: Boolean,
            default: true
        }
    },

    computed: {
        isListItemPreview() {
            return this.containerOptions.previewType === 'media-grid-preview-as-list';
        },

        gridItemListeners() {
            return {
                click: this.emitClickedEvent
            };
        },

        itemTitle() {
            return this.item.name;
        },

        mediaItemClasses() {
            return {
                'is--selected': this.selected
            };
        },

        mediaItemContentClasses() {
            return {
                'is--grid': !this.isListItemPreview,
                'is--list': this.isListItemPreview
            };
        },

        selectedIndicatorClasses() {
            return {
                'selected-indicator--visible': this.containerOptions.selectionInProgress
            };
        }
    },

    methods: {
        emitClickedEvent(originalDomEvent) {
            const target = originalDomEvent.target;
            if (this.showContextMenuButton) {
                const el = this.$refs.swContextButton.$el;
                if ((el === target) || el.contains(target)) {
                    return;
                }
            }

            if (!this.selected) {
                this.$emit('sw-media-grid-item-clicked', {
                    originalDomEvent,
                    item: this.item
                });
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        doSelectItem(originalDomEvent) {
            if (!this.selected) {
                this.selectItem(originalDomEvent);
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        selectItem(originalDomEvent) {
            this.$emit('sw-media-grid-item-selection-add', {
                originalDomEvent,
                item: this.item
            });
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('sw-media-grid-item-selection-remove', {
                originalDomEvent,
                item: this.item
            });
        }
    }
});
