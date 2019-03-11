import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-product-media-form.html.twig';
import './sw-product-media-form.scss';

Component.register('sw-product-media-form', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            columnCount: 7,
            columnWidth: 90
        };
    },

    computed: {
        mediaItems() {
            const mediaItems = this.product.media.slice();
            const placeholderCount = this.getPlaceholderCount(this.columnCount);
            if (placeholderCount === 0) {
                return mediaItems;
            }

            for (let i = 0; i < placeholderCount; i += 1) {
                mediaItems.push(this.createPlaceholderMedia(mediaItems));
            }

            return mediaItems;
        },

        productMediaStore() {
            return this.product.getAssociation('media');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        mediaStore() {
            return State.getStore('media');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const that = this;
            this.$device.onResize({
                listener() {
                    that.updateColumnCount();
                },
                component: this
            });
            this.updateColumnCount();
        },

        updateColumnCount() {
            this.$nextTick(() => {
                const cssColumns = window.getComputedStyle(this.$refs.grid, null)
                    .getPropertyValue('grid-template-columns')
                    .split(' ');
                this.columnCount = cssColumns.length;
                this.columnWidth = cssColumns[0];
            });
        },

        getPlaceholderCount(columnCount) {
            if (this.product.media.length + 3 < columnCount * 2) {
                columnCount *= 2;
            }
            const placeholderCount = columnCount - ((this.product.media.length + 3) % columnCount);

            if (placeholderCount === columnCount) {
                return 0;
            }

            return placeholderCount;
        },

        createPlaceholderMedia(mediaItems) {
            return {
                isPlaceholder: true,
                isCover: mediaItems.length === 0,
                media: {
                    isPlaceholder: true,
                    name: ''
                },
                mediaId: mediaItems.length
            };
        },

        onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            this.product.isLoading = true;
            this.mediaStore.sync().then(() => {
                data.forEach((upload) => {
                    if (this.product.media.some((pMedia) => {
                        return pMedia.mediaId === upload.targetId;
                    })) {
                        return;
                    }

                    this.product.media.push(this.buildProductMedia(upload.targetId));
                });
                this.product.isLoading = false;

                this.uploadStore.runUploads(this.product.id);
            });
        },

        onMediaUploadButtonOpenSidebar() {
            this.$root.$emit('sw-product-media-form-open-sidebar');
        },

        getKey(media) {
            return media.id;
        },

        buildProductMedia(mediaId) {
            const productMedia = this.productMediaStore.create();
            productMedia.mediaId = mediaId;

            if (this.product.media.length === 0) {
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            } else {
                productMedia.position = this.product.media.length + 1;
            }

            return productMedia;
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then(() => {
                this.$forceUpdate();
            });
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.product.media.find((productMedia) => {
                return productMedia.mediaId === uploadTask.targetId;
            });
            if (toRemove) {
                this.removeFile(toRemove);
            }
            this.product.isLoading = false;
        },

        removeFile(mediaItem) {
            const key = mediaItem.id;
            const item = this.product.media.find((e) => {
                return e.id === key;
            });

            this.product.media = this.product.media.filter((e) => e.id !== key && e !== key);
            if (this.isCover(item)) {
                if (this.product.media.length === 0) {
                    this.product.coverId = null;
                } else {
                    this.product.coverId = this.product.media[0].id;
                }
            }
            item.delete();
        },

        isCover(productMedia) {
            if (productMedia.isPlaceholder) {
                return productMedia.isCover;
            }

            if (this.product.coverId === null) {
                this.product.coverId = productMedia.id;
            }

            return this.product.coverId === productMedia.id;
        },

        markMediaAsCover(productMedia) {
            this.product.coverId = productMedia.id;
        }
    }
});
