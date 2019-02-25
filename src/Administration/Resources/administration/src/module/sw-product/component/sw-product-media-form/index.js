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
            columnWidth: 90,
            unsavedEntities: []
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

    beforeDestroy() {
        this.onBeforeDestroy();
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

        onBeforeDestroy() {
            this.unsavedEntities.forEach((entity) => {
                this.uploadStore.removeUpload(entity.taskId);
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

        onUploadsAdded({ uploadTag, data }) {
            if (data.length === 0) {
                return;
            }

            this.product.isLoading = true;
            this.mediaStore.sync().then(() => {
                this.uploadStore.runUploads(uploadTag).then(() => {
                    data.forEach((upload) => {
                        if (!upload.entity.isDeleted) {
                            this.product.media.push(this.buildProductMedia(upload.entity));
                            this.product.isLoading = false;
                        }
                    });
                });
            });
        },

        onMediaUploadButtonOpenSidebar() {
            this.$root.$emit('sw-product-media-form-open-sidebar');
        },

        getKey(media) {
            return media.id;
        },

        buildProductMedia(mediaEntity) {
            const productMedia = this.productMediaStore.create();
            productMedia.mediaId = mediaEntity.id;

            if (this.product.media.length === 0) {
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            } else {
                productMedia.position = this.product.media.length + 1;
            }

            return productMedia;
        },

        addImageToPreview(sourceURL, productMedia) {
            const canvas = document.createElement('canvas');
            const columnWidth = this.columnWidth.split('px')[0];
            const size = this.isCover(productMedia) ? columnWidth * 2 : columnWidth;
            const img = new Image();
            img.onload = () => {
                // resize image with aspect ratio
                const dimensions = this.getImageDimensions(img, size);
                canvas.setAttribute('width', dimensions.width);
                canvas.setAttribute('height', dimensions.height);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(
                    img, 0, 0, canvas.width, canvas.height
                );

                productMedia.media.url = canvas.toDataURL();
                productMedia.isLoading = false;

                this.$forceUpdate();
            };
            img.src = sourceURL;
        },

        successfulUpload(mediaEntity) {
            this.mediaStore.getByIdAsync(mediaEntity.id).then(() => {
                this.$forceUpdate();
            });
        },

        onMediaReplaced(mediaEntity) {
            if (this.mediaItems.some((e) => {
                return e.mediaId === mediaEntity.id;
            })) {
                this.createNotificationInfo({
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });
                return;
            }

            const productMedia = this.buildProductMedia(mediaEntity);
            productMedia.isLoading = false;
            this.product.media.push(productMedia);

            this.product.save();
        },

        onUploadFailed(mediaEntity) {
            const toRemove = this.product.media.find((productMedia) => {
                return productMedia.mediaId === mediaEntity.id;
            });

            if (toRemove) {
                this.removeFile(toRemove);
            }
            this.product.isLoading = false;
        },

        getImageDimensions(img, size) {
            if (img.width > img.height) {
                return {
                    height: size,
                    width: size * (img.width / img.height)
                };
            }

            return {
                width: size,
                height: size * (img.height / img.width)
            };
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

            const upload = this.unsavedEntities.find((e) => {
                return e.productMediaId === key;
            });

            if (upload) {
                this.uploadStore.removeUpload(upload.taskId);
            }

            if (typeof item !== 'string') {
                item.delete();
            }
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
