import { Mixin } from 'src/core/shopware';

Mixin.register('selectable-media-item', {
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
