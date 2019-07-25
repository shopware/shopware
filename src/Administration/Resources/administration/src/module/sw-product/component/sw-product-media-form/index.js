import { Component, Mixin, State } from 'src/core/shopware';
import { mapGetters } from 'vuex';
import template from './sw-product-media-form.html.twig';
import './sw-product-media-form.scss';

Component.register('sw-product-media-form', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        productId: {
            type: String,
            required: true
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            isMediaLoading: true,
            product: {},
            columnCount: 7,
            columnWidth: 90
        };
    },

    computed: {
        productFromStore() {
            const state = this.$store.state.swProductDetail;

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.product;
        },

        ...mapGetters('swProductDetail', {
            isStoreLoading: 'isLoading'
        }),

        isLoading() {
            let isActualLoading = false;

            if (this.isMediaLoading) {
                isActualLoading = true;
            }

            if (this.isStoreLoading) {
                isActualLoading = true;
            }

            return isActualLoading;
        },

        productStore() {
            return State.getStore('product');
        },

        mediaItems() {
            const mediaItems = this.productMedia.slice();
            const placeholderCount = this.getPlaceholderCount(this.columnCount);
            if (placeholderCount === 0) {
                return mediaItems;
            }

            for (let i = 0; i < placeholderCount; i += 1) {
                mediaItems.push(this.createPlaceholderMedia(mediaItems));
            }

            return mediaItems;
        },

        productMedia() {
            return this.product.media;
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

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        mountedComponent() {
            this.loadMedia();

            const that = this;
            this.$device.onResize({
                listener() {
                    that.updateColumnCount();
                },
                component: this
            });
            this.updateColumnCount();

            this.$root.$on('media-added', (mediaId) => {
                // add media
                this.addMediaWithId(mediaId);
            });
        },

        destroyedComponent() {
            this.$root.$off('media-added');
        },

        addMediaWithId(mediaId) {
            const foundMedia = this.product.media.find(media => media.mediaId === mediaId);
            if (foundMedia) {
                // check if media exists in productFromStore
                const mediaProductFromStore = this.productFromStore.media.find((media) => media.mediaId === mediaId);
                if (!mediaProductFromStore) {
                    this.$emit('media-drop', foundMedia);
                }

                return Promise.resolve();
            }

            return new Promise((resolve) => {
                // get media
                this.mediaStore.getByIdAsync(mediaId).then((res) => {
                    const responseMedia = res;

                    // set mediaId
                    responseMedia.setData({ mediaId: responseMedia.id });

                    // push it to existing product
                    this.product.media.push(responseMedia);

                    // set first item as cover
                    if (!this.productFromStore.coverId) {
                        this.markMediaAsCover(responseMedia);
                    }

                    // check if media exists in productFromStore
                    const mediaProductFromStore = this.productFromStore.media.find((media) => media.mediaId === mediaId);
                    if (!mediaProductFromStore) {
                        this.$emit('media-drop', responseMedia);
                    }

                    resolve();
                });
            });
        },

        loadMedia() {
            this.isMediaLoading = true;

            // create new empty product
            this.product = this.productStore.create();

            // get existing media from vuex store
            const existingCoverId = this.productFromStore.coverId || '';
            const mediaItemsFromStore = this.productFromStore.media;

            // add cover to product
            this.product.coverId = existingCoverId;

            const mediaPromises = mediaItemsFromStore.map((media) => {
                return new Promise((resolve) => {
                    // get media
                    this.mediaStore.getByIdAsync(media.mediaId).then((res) => {
                        const responseMedia = res;

                        // set mediaId
                        responseMedia.setData({
                            productMediaId: media.id,
                            mediaId: responseMedia.id
                        });

                        // push it to existing product
                        this.product.media.push(responseMedia);
                        resolve();
                    });
                });
            });

            // get all media items from api
            Promise.all(mediaPromises).then(() => {
                this.isMediaLoading = false;
                this.updateColumnCount();
            });

            return true;
        },

        updateColumnCount() {
            this.$nextTick(() => {
                if (this.isLoading) {
                    return false;
                }

                const cssColumns = window.getComputedStyle(this.$refs.grid, null)
                    .getPropertyValue('grid-template-columns')
                    .split(' ');
                this.columnCount = cssColumns.length;
                this.columnWidth = cssColumns[0];

                return true;
            });
        },

        getPlaceholderCount(columnCount) {
            if (this.productMedia.length + 3 < columnCount * 2) {
                columnCount *= 2;
            }
            const placeholderCount = columnCount - ((this.productMedia.length + 3) % columnCount);

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
                this.product.isLoading = false;

                this.uploadStore.runUploads(this.product.id);
            });
        },

        onMediaUploadButtonOpenSidebar() {
            this.$root.$emit('sidebar-toggle-open');
        },

        getKey(media) {
            return media.id;
        },

        buildProductMedia(mediaId) {
            const productMedia = this.productMediaStore.create();
            productMedia.mediaId = mediaId;

            if (this.productMedia.length === 0) {
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            } else {
                productMedia.position = this.productMedia.length + 1;
            }

            return productMedia;
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((mediaItem) => {
                this.$emit('media-drop', mediaItem);
                return true;
            });
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.productMedia.find((productMedia) => {
                return productMedia.mediaId === uploadTask.targetId;
            });
            if (toRemove) {
                this.removeFile(toRemove);
            }
            this.product.isLoading = false;
        },

        removeFile(mediaItem) {
            this.product.media = this.product.media.filter((m) => m.id !== mediaItem.id);
            this.productFromStore.media.remove(mediaItem.id);

            this.removeCover();

            return true;
        },

        removeCover() {
            this.productFromStore.coverId = null;

            // if another media exists
            if (this.productFromStore.media.length > 0) {
                // set first media item as cover
                this.productFromStore.coverId = this.productFromStore.media[0].id;
            }
        },

        isCover(productMedia) {
            if (productMedia.isPlaceholder) {
                return productMedia.isCover;
            }

            if (this.productFromStore.coverId === null && this.productFromStore.media.length > 0) {
                this.productFromStore.coverId = (productMedia.productMediaId || productMedia.id);
            }

            return this.productFromStore.coverId === (productMedia.productMediaId || productMedia.id);
        },

        markMediaAsCover(productMedia) {
            this.product.coverId = (productMedia.productMediaId || productMedia.id);
            this.productFromStore.coverId = (productMedia.productMediaId || productMedia.id);
        },

        onDropMedia(dragData) {
            this.$emit('media-drop', dragData.mediaItem);
        }
    }
});
