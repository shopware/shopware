import template from './sw-media-list-selection-item.html.twig';
import './sw-media-list-selection-item.scss';

/**
 * @private
 * @description Component which renders an image.
 * @status ready
 */
export default {
    name: 'sw-media-list-selection-item',
    template,

    props: {
        item: {
            required: true
        },

        isPlaceholder: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        productImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder
            };
        },

        mediaItem() {
            if (typeof this.item === 'string') {
                return this.item;
            }
            return this.item.media;
        },

        sourceId() {
            return this.item.mediaId || this.item.targetId || this.item.id;
        }
    }
};
