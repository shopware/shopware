import { Component } from 'src/core/shopware';
import template from './sw-media-grid-media-item.html.twig';
import './sw-media-grid-media-item.less';
import domUtils from '../../../../core/service/utils/dom.utils';

Component.register('sw-media-grid-media-item', {
    template,

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.type === 'media';
            }
        },

        multiSelectInProgress: {
            required: true,
            type: Boolean
        },

        selected: {
            type: Boolean,
            required: true
        },

        isList: {
            type: Boolean,
            required: false,
            default: false
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    computed: {
        mediaPreviewClasses() {
            return {
                'is--highlighted': this.selected && this.multiSelectInProgress
            };
        },

        selectedIndicatorClasses() {
            return {
                'selected-indicator--visible': this.multiSelectInProgress
            };
        }
    },

    methods: {
        handleGridItemClick({ originalDomEvent }) {
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
        },

        emitPlayEvent(originalDomEvent) {
            if (!this.selected) {
                this.$emit('sw-media-grid-item-play', {
                    originalDomEvent,
                    item: this.item
                });
                return;
            }

            this.removeFromSelection(originalDomEvent);
        },

        showItemDetails(originalDomEvent) {
            this.$emit('sw-media-grid-media-item-show-details', {
                originalDomEvent,
                item: this.item
            });
        },

        copyItemLink() {
            domUtils.copyToClipboard(this.item.url);
        },

        deleteItem(originalDomEvent) {
            this.$emit('sw-media-grid-media-item-delete', {
                originalDomEvent,
                item: this.item
            });
        },

        replaceItem(originalDomEvent) {
            this.$emit('sw-media-grid-media-item-replace', {
                originalDomEvent,
                item: this.item
            });
        }
    }
});
