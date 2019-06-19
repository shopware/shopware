import { Component, Mixin, State } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import utils from 'src/core/service/util.service';
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

    watch: {
        'product.media': {
            deep: true,
            handler: utils.debounce(function saveProduct() {
                const changes = Object.getOwnPropertyNames(this.product.getChanges());

                const translatedIndex = changes.indexOf('translated');
                if (translatedIndex >= 0) {
                    changes.splice(translatedIndex, 1);
                }

                if (changes.length > 0) {
                    if (!this.localMode) {
                        this.product.save();
                    }
                }
            }, 500)
        }
    },

    computed: {
        ...mapState('swProductDetail', [
            'localMode',
            'parentProduct'
        ]),

        ...mapState('swProductDetail', {
            productFromStore: state => state.product
        }),

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
            if (this.product.media.find(media => media.mediaId === mediaId)) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                // get media
                this.mediaStore.getByIdAsync(mediaId).then((res) => {
                    const responseMedia = res;

                    // set mediaId
                    responseMedia.setData({ mediaId: responseMedia.id });

                    // set first item as cover
                    if (this.product.media.length <= 0) {
                        this.markMediaAsCover(responseMedia);
                    }

                    // push it to existing product
                    this.product.media.push(responseMedia);

                    resolve();
                });
            });
        },

        loadMedia() {
            if (this.localMode) {
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
                            responseMedia.setData({ mediaId: responseMedia.id });

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
            }

            this.isMediaLoading = true;
            this.product = this.productStore.getById(this.productId);
            this.product.getAssociation('media').getList({
                page: 1,
                limit: 50,
                sortBy: 'position',
                sortDirection: 'ASC'
            });
            this.isMediaLoading = false;

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
                data.forEach((upload) => {
                    if (this.productMedia.some((pMedia) => {
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

            this.productFromStore.media.remove(mediaItem.id);

            return true;
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
            if (this.localMode) {
                this.productFromStore.coverId = productMedia.id;
            }
            this.product.coverId = productMedia.id;
        },

        onDropMedia(dragData) {
            this.$emit('media-drop', dragData.mediaItem);
        }
    }
});
