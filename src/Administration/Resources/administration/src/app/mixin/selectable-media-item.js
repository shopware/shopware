import { Mixin } from 'src/core/shopware';

Mixin.register('selectable-media-item', {
    props: {
        item: {
            type: Object,
            required: true
        },

        showSelectionIndicator: {
            type: Boolean,
            required: false,
            default: false
        },

        selected: {
            type: Boolean,
            required: false,
            default: false
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

    methods: {
        handleGridItemClick(originalDomEvent) {
            this.$emit('sw-media-item-clicked', {
                originalDomEvent,
                item: this.item
            });
        },

        selectItem(originalDomEvent) {
            this.$emit('sw-media-item-selection-add', {
                originalDomEvent,
                item: this.item
            });
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('sw-media-item-selection-remove', {
                originalDomEvent,
                item: this.item
            });
        }
    }
});
