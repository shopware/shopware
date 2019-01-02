import template from './sw-product-image.html.twig';
import './sw-product-image.scss';

/**
 * @public
 * @description Component which renders an image.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-image :item="item" isCover="true"></sw-image>
 */
export default {
    name: 'sw-product-image',
    template,

    props: {
        item: {
            required: true
        },

        isCover: {
            type: Boolean,
            required: false,
            default: false
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
                'is--placeholder': this.isPlaceholder,
                'is--cover': this.isCover
            };
        },

        mediaItem() {
            if (typeof this.item === 'string') {
                return this.item;
            }
            return this.item.media;
        }
    }
};
