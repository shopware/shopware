import template from './sw-media-list-selection-item.html.twig';
import './sw-media-list-selection-item.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Component which renders an image.
 * @status ready
 */
Component.register('sw-media-list-selection-item', {
    template,

    props: {
        item: {
            required: true
        },

        hideActions: {
            type: Boolean,
            required: false,
            default: false
        },

        hideTooltip: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        isPlaceholder() {
            return !!this.item.isPlaceholder;
        },

        productImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder
            };
        },

        sourceId() {
            return this.item.mediaId || this.item.targetId || this.item.id;
        }
    }
});
