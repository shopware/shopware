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
        }
    },

    computed: {
        isListItemPreview() {
            return this.containerOptions.previewType === 'media-grid-preview-as-list';
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
        },

        gridItemListeners() {
            return {
                click: this.doMainAction
            };
        }
    },

    methods: {
        doMainAction(originalDomEvent) {
            this.doSelectItem(originalDomEvent);
        },

        doSelectItem(originalDomEvent) {
            if (!this.selected ||
                ['SVG', 'BUTTON'].includes(originalDomEvent.target.tagName.toUpperCase())
            ) {
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
