import { Component } from 'src/core/shopware';
import template from './sw-media-grid-media-item.html.twig';

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
        doMainAction(event) {
            if (this.containerOptions.editable) {
                if (!this.$refs.inputItemName.disabled) {
                    return;
                }

                if (this.fromBlur) {
                    this.fromBlur = false;
                    return;
                }
            }
            this.doSelectItem(event);
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
        signalItemChanges(event) {
            this.emitItemChangeEvent(event, 'changed-name', {
                name: this.$refs.inputItemName.value
            });
        }
    }
});
