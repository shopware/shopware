import template from './sw-product-variants-media-upload.html.twig';
import './sw-product-variants-media-upload.scss';

Shopware.Component.extend('sw-product-variants-media-upload', 'sw-media-upload-v2', {
    template,

    props: {
        source: {
            type: Object,
            required: true
        },

        parentProduct: {
            type: Object,
            required: true
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showMediaModal: false
        };
    },

    computed: {
        product() {
            if (this.isInherited) {
                return this.parentProduct;
            }

            return this.source;
        },

        mediaSource() {
            if (!this.product) {
                return [];
            }

            return this.product.media;
        },
        cover() {
            if (!this.product) {
                return null;
            }
            const coverId = this.product.cover ? this.product.cover.mediaId : this.product.coverId;
            return this.product.media.find(media => media.id === coverId);
        }
    },

    methods: {
        isCover(productMedia) {
            const coverId = this.product.cover ? this.product.cover.id : this.product.coverId;

            if (this.product.media.length === 0) {
                return false;
            }

            return productMedia.id === coverId;
        },

        markMediaAsCover(productMedia) {
            this.product.cover = productMedia;
            this.product.coverId = productMedia.id;
        },

        removeMedia(productMedia) {
            if (this.product.coverId === productMedia.id) {
                this.product.cover = null;
                this.product.coverId = null;
            }

            if (this.product.coverId === null && this.product.media.length > 0) {
                this.product.coverId = this.product.media.first().id;
            }

            this.product.media.remove(productMedia.id);
        }
    }
});
