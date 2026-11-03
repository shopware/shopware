import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-product-media-form,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-product-media-form',
    extends: 'sw-product-media-form',
    load: () => import('./src/sw-bulk-edit-product-media-form'),
    public: {
        props: [
            'disabled',
            'isInherited',
            'fileAccept',
        ],
        emits: [
            'media-open',
        ],
        blocks: [
            'sw_product_media_form_cover_preview',
            'sw_product_media_form',
            'sw_product_media_form_upload',
            'sw_product_media_form_grid',
            'sw_product_media_form_cover_image',
            'sw_product_media_form_grid_items',
            'sw_product_media_form_grid_item',
        ],
        computed: [
            'product',
            'galleryItems',
            'mediaItems',
            'cover',
            'isStoreLoading',
            'isLoading',
            'productMediaRepository',
            'mediaRepository',
            'productMedia',
            'productMediaStore',
            'gridAutoRows',
            'currentCoverID',
        ],
        methods: [
            'onCreated',
            'onOpenMedia',
            'updateColumnCount',
            'getPlaceholderCount',
            'createPlaceholderMedia',
            'buildProductMedia',
            'successfulUpload',
            'createMediaAssociation',
            'onUploadFailed',
            'removeCover',
            'isCover',
            'isSpatial',
            'isArReady',
            'removeFile',
            'markMediaAsCover',
            'onDropMedia',
            'onMediaItemDragSort',
            'moveGalleryItem',
            'updateMediaItemPositions',
        ],
    },
});
