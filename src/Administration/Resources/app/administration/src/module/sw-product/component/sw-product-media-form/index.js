import template from './sw-product-media-form.html.twig';
import './sw-product-media-form.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-media-form', {
    template,
    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
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
            isMediaLoading: false,
            columnCount: 5,
            columnWidth: 90
        };
    },

    computed: {
        product() {
            const state = Shopware.State.get('swProductDetail');

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.product;
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

        cover() {
            if (!this.product) {
                return null;
            }
            return this.product.media.find(media => media.id === this.product.coverId);
        },

        ...mapGetters('swProductDetail', {
            isStoreLoading: 'isLoading'
        }),

        isLoading() {
            return this.isMediaLoading || this.isStoreLoading;
        },

        productMediaRepository() {
            return this.repositoryFactory.create('product_media');
        },

        productMedia() {
            if (!this.product) {
                return [];
            }
            return this.product.media;
        },

        productMediaStore() {
            return this.product.getAssociation('media');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        currentCoverID() {
            const coverMediaItem = this.productMedia.find(coverMedium => coverMedium.media.id === this.product.coverId);

            return coverMediaItem.id;
        }
    },

    methods: {
        onMediaUploadButtonOpenSidebar() {
            this.$root.$emit('sidebar-toggle-open');
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

            let placeholderCount = columnCount;

            if (this.productMedia.length !== 0) {
                placeholderCount = columnCount - ((this.productMedia.length) % columnCount);
                if (placeholderCount === columnCount) {
                    return 0;
                }
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
                mediaId: mediaItems.length.toString()
            };
        },

        buildProductMedia(mediaId) {
            this.isLoading = true;

            const productMedia = this.productMediaStore.create();
            productMedia.mediaId = mediaId;

            if (this.productMedia.length === 0) {
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            } else {
                productMedia.position = this.productMedia.length + 1;
            }
            this.isLoading = false;

            return productMedia;
        },

        successfulUpload({ targetId }) {
            // on replace
            if (this.product.media.find((productMedia) => productMedia.mediaId === targetId)) {
                return;
            }

            const productMedia = this.createMediaAssociation(targetId);
            this.product.media.add(productMedia);
        },

        createMediaAssociation(targetId) {
            const productMedia = this.productMediaRepository.create(Shopware.Context.api);

            productMedia.productId = this.product.id;
            productMedia.mediaId = targetId;

            if (this.product.media.length <= 0) {
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            } else {
                productMedia.position = this.product.media.length;
            }
            return productMedia;
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.product.media.find((productMedia) => {
                return productMedia.mediaId === uploadTask.targetId;
            });
            if (toRemove) {
                if (this.product.coverId === toRemove.id) {
                    this.product.coverId = null;
                }
                this.product.media.remove(toRemove.id);
            }
            this.product.isLoading = false;
        },

        removeCover() {
            this.product.coverId = null;

            // if another media exists
            if (this.productFromStore.media.length > 0) {
                // set first media item as cover
                this.productFromStore.coverId = this.productFromStore.media[0].id;
            }
        },

        isCover(productMedia) {
            if (this.product.media.length === 0) {
                return false;
            }

            return productMedia.id === this.product.coverId;
        },

        removeFile(productMedia) {
            // remove cover id if mediaId matches
            if (this.product.coverId === productMedia.id) {
                this.product.coverId = null;
            }

            this.product.media.remove(productMedia.id);
        },

        markMediaAsCover(productMedia) {
            this.product.coverId = (productMedia.productMediaId || productMedia.id);
            this.productFromStore.coverId = (productMedia.productMediaId || productMedia.id);
        },

        onDropMedia(dragData) {
            if (this.product.media.find((productMedia) => productMedia.mediaId === dragData.id)) {
                return;
            }

            const productMedia = this.createMediaAssociation(dragData.mediaItem.id);
            if (this.product.media.length === 0) {
                // set media item as cover
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            }

            this.product.media.add(productMedia);
        },

        onMediaItemDragSort(dragData, dropData, validDrop) {
            if (validDrop !== true) {
                return;
            }
            this.product.media.moveItem(dragData.position, dropData.position);

            this.updateMediaItemPositions();
        },

        updateMediaItemPositions() {
            this.productMedia.forEach((medium, index) => {
                medium.position = index;
            });
        }
    }
});
