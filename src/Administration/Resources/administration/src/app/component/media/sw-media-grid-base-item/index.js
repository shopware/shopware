import { Component } from 'src/core/shopware';
import template from './sw-media-grid-base-item.html.twig';
import './sw-media-grid-base-item.less';

/**
 * @private
 */
Component.register('sw-media-grid-base-item', {
    template,

    props: {
        isList: {
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
                'is--list': this.isList
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

            this.$emit('sw-media-grid-base-item-clicked', {
                originalDomEvent
            });
        }
    }
});
