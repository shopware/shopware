import { Component } from 'src/core/shopware';
import template from './sw-media-grid-item.twig';
import './sw-media-grid-item.less';

Component.register('sw-media-grid-item', {
    template,

    data() {
        return {
            fromBlur: false
        };
    },

    props: {
        showInline: {
            required: false,
            type: Boolean,
            default: false
        },
        selected: {
            type: Boolean,
            required: true
        },
        mediaItem: {
            required: true,
            type: Object
        },
        showCheckbox: {
            required: false,
            type: Boolean,
            default: false
        }
    },

    computed: {
        mediaItemClass() {
            return {
                'sw-media-grid-item': true,
                'sw-media-grid-item--selected': this.selected
            };
        },
        mediaItemContentClass() {
            return {
                'sw-media-grid-item__content': true,
                'is--grid': !this.showInline,
                'is--list': this.showInline
            };
        },
        mediaItemCheckboxClass() {
            return {
                'sw-media-grid-item__checkbox': true,
                'checkbox-is--visible': this.showCheckbox
            };
        }
    },

    methods: {
        doSelectItem(event) {
            if (!this.$refs.inputItemName.disabled) {
                return;
            }

            if (this.fromBlur) {
                this.fromBlur = false;
                return;
            }

            if (!this.selected ||
                ['SVG', 'BUTTON'].includes(event.target.tagName.toUpperCase())
            ) {
                this.selectItem();
                return;
            }

            this.removeSelection();
        },
        selectItem() {
            this.$emit('media-item-add-to-selection', this.mediaItem);
        },
        removeSelection() {
            this.$emit('media-item-remove-from-selection', this.mediaItem);
        },
        startInlineEdit() {
            const input = this.$refs.inputItemName;

            input.disabled = false;
            this.selectItem();
            input.focus();
        },
        cancelInlineEdit() {
            this.fromBlur = true;
            this.$refs.inputItemName.value = this.mediaItem.name;
            this.$refs.inputItemName.disabled = true;
        },
        signalItemNameChange() {
            this.$emit('media-item-name-changed', this.mediaItem);
        }
    }
});
