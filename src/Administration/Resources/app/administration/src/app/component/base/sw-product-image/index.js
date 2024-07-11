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

    compatConfig: Shopware.compatConfig,

    props: {
        mediaId: {
            type: String,
            required: true,
        },

        /**
         * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
         */
        isSpatial: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
         */
        isArReady: {
            type: Boolean,
            required: false,
            default: false,
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
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        productImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder,
                'is--cover': this.isCover && this.showCoverLabel,
                'is--spatial': this.isSpatial,
            };
        },
    },
});
