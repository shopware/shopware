import { Component } from 'src/core/shopware';

Component.register('sw-media-entity-mapper', {
    $entityMapping: {
        media: 'sw-media-media-item',
        media_folder: 'sw-media-folder-item'
    },

    render(h) {
        return h(
            'div',
            {
                class: 'sw-media-entity'
            },
            [
                h(
                    this.$options.$entityMapping[this.item.entityName],
                    {
                        props: Object.assign({}, this.$attrs, { item: this.item }),
                        on: this.$listeners
                    },
                    []
                )
            ]
        );
    },

    props: {
        item: {
            type: Object,
            required: true,
            validator(value) {
                return !!value.entityName;
            }
        }
    },

    methods: {
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
