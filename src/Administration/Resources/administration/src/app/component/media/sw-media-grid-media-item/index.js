import { Component } from 'src/core/shopware';
import template from './sw-media-grid-media-item.html.twig';
import domUtils from '../../../../core/service/utils/dom.utils';

Component.extend('sw-media-grid-media-item', 'sw-media-grid-item', {
    template,

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.type !== undefined && value.type === 'media';
            }
        }
    },

    data() {
        return {
            fromBlur: false
        };
    },

    computed: {
        gridItemListeners() {
            return {
                click: this.doMainAction,
                dblclick: this.startInlineEdit
            };
        }
    },

    methods: {
        doMainAction(originalDomEvent) {
            if (this.containerOptions.editable) {
                if (!this.$refs.inputItemName.disabled) {
                    return;
                }

                if (this.fromBlur) {
                    this.fromBlur = false;
                    return;
                }
            }

            this.emitClickedEvent(originalDomEvent);
        },

        startInlineEdit() {
            if (this.containerOptions.editable) {
                const input = this.$refs.inputItemName;

                this.selectItem();
                input.disabled = false;
                input.focus();
            }
        },

        cancelInlineEditFromBlur() {
            this.fromBlur = true;
            this.cancelInlineEdit();
        },

        cancelInlineEdit() {
            this.$refs.inputItemName.value = this.item.name;
            this.$refs.inputItemName.disabled = true;
        },

        emitNameChanged(originalDomEvent) {
            this.$emit('sw-media-grid-media-item-change-name', {
                originalDomEvent,
                item: this.item,
                newName: this.$refs.inputItemName.value
            });
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
