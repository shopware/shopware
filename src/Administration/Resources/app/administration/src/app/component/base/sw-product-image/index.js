import template from './sw-product-image.html.twig';
import './sw-product-image.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Component which renders an image.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-image :item="item" isCover="true"></sw-image>
 */
Component.register('sw-product-image', {
    template,

    props: {
        mediaId: {
            type: String,
            required: true,
        },

        isCover: {
            type: Boolean,
            required: false,
            default: false,
        },

        isPlaceholder: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        productImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder,
                'is--cover': this.isCover,
            };
        },
    },
});
