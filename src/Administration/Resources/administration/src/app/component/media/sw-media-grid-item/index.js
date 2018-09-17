import { Component } from 'src/core/shopware';
import template from './sw-media-grid-item.html.twig';
import './sw-media-grid-item.less';

Component.register('sw-media-grid-item', {
    template,

    props: {
        isList: {
            required: false,
            type: Boolean,
            default: false
        },

        selected: {
            type: Boolean,
            required: false,
            default: false
        },

        showContextMenuButton: {
            required: false,
            type: Boolean,
            default: true
        }
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--selected': this.selected,
                'is--grid': !this.isListItemPreview,
                'is--list': this.isListItemPreview
            };
        },

        mediaItemContentClasses() {
            return {
                'is--grid': !this.isList,
                'is--list': this.isList
            };
        }
    },

    methods: {
        emitClickedEvent(originalDomEvent) {
            const target = originalDomEvent.target;
            if (this.showContextMenuButton) {
                const el = this.$refs.swContextButton.$el;
                if ((el === target) || el.contains(target)) {
                    return;
                }
            }

            this.$emit('sw-media-grid-item-clicked', {
                originalDomEvent
            });
        }
    }
});
