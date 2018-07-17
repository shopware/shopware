import { Component } from 'src/core/shopware';
import mediaGridPreviewTypes from '../mediaGridPreviewTypes';
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

    data() {
        return {};
    },

    computed: {
        isListItemPreview() {
            return this.containerOptions.previewType === mediaGridPreviewTypes.MEDIA_GRID_PREVIEW_TYPE_LIST;
        },
        itemTitle() {
            return this.item.name;
        },
        mediaItemClass() {
            return {
                'sw-media-grid-item': true,
                'sw-media-grid-item--selected': this.selected
            };
        },
        mediaItemContentClass() {
            return {
                'sw-media-grid-item__content': true,
                'is--grid': !this.isListItemPreview,
                'is--list': this.isListItemPreview
            };
        },
        mediaItemCheckboxClass() {
            return {
                'sw-media-grid-item__selected-indicator': true,
                'selected-indicator-is--visible': this.containerOptions.selectionInProgress
            };
        },
        gridItemListeners() {
            return {
                click: this.doMainAction
            };
        }
    },

    methods: {
        doMainAction(event) {
            this.doSelectItem(event);
        },
        doSelectItem(event) {
            if (!this.selected ||
                ['SVG', 'BUTTON'].includes(event.target.tagName.toUpperCase())
            ) {
                this.selectItem();
                return;
            }

            this.removeFromSelection();
        },
        selectItem() {
            this.$emit('media-item-add-to-selection', this.item);
        },
        removeFromSelection() {
            this.$emit('media-item-remove-from-selection', this.item);
        },
        emitItemChangeEvent(event, action, parameters) {
            this.emitMediaGridItemEvent(event, action, false, parameters);
        },
        emitBatchEvent(event, action, parameters) {
            this.emitMediaGridItemEvent(event, action, true, parameters);
        },
        emitMediaGridItemEvent(originalDomEvent, actionName, isBatch, parameters) {
            this.$emit('media-grid-item-event', {
                originalDomEvent: originalDomEvent,
                context: {
                    action: actionName,
                    isBatch: isBatch
                },
                target: this.item,
                parameters: parameters
            });
        }
    }
});
