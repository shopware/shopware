import template from './sw-product-image.html.twig';
import './sw-product-image.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
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

        showCoverLabel: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        productImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder,
                'is--cover': this.isCover && this.showCoverLabel,
            };
        },
    },
});
