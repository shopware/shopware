import template from './sw-media-list-selection-item-v2.html.twig';
import './sw-media-list-selection-item-v2.scss';

/**
 * @private
 * @description Component which renders an image.
 * @status ready
 * @package content
 */
export default {
    template,

    props: {
        // FIXME: add type to property
        // eslint-disable-next-line vue/require-prop-types
        item: {
            required: true,
        },

        hideActions: {
            type: Boolean,
            required: false,
            default: false,
        },

        hideTooltip: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        isPlaceholder() {
            return !!this.item.isPlaceholder;
        },

        productImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder,
            };
        },

        sourceId() {
            return this.item.mediaId || this.item.targetId || this.item.id;
        },
    },
};
