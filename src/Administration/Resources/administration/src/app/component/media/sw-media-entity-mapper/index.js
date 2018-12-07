import { Component } from 'src/core/shopware';

Component.register('sw-media-entity-mapper', {
    functional: true,

    render(createElement, context) {
        function mapEntity() {
            const entityMapping = {
                media: 'sw-media-media-item',
                media_folder: 'sw-media-folder-item'
            };
            return entityMapping[context.props.item.entityName];
        }

        Object.assign(context.data.attrs, context.props);
        return createElement(
            'div',
            {
                class: 'sw-media-entity'
            },
            [
                createElement(
                    mapEntity(),
                    context.data,
                    context.slots().default
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
