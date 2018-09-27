import { Component } from 'src/core/shopware';
import template from './sw-media-grid-item.html.twig';
import './sw-media-grid-item.less';

Component.register('sw-media-grid-item', {
    template,

    props: {
        isList: {
            type: Boolean,
            required: false,
            default: false
        },

        selected: {
            type: Boolean,
            required: false,
            default: false
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            default: true
        },

        isLoading: {
            type: Boolean,
            required: true
        }
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--grid': !this.isList,
                'is--list': this.isList
            };
        },

        isSelectedClass() {
            return {
                'is--selected': this.selected
            };
        }
    },

    methods: {
        emitClickedEvent(originalDomEvent) {
            const target = originalDomEvent.target;
            if (this.showContextMenuButton && !this.isLoading) {
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
