import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-product-media',
    load: () => import('./src/sw-bulk-edit-product-media'),
    public: {
        props: [
            'disabled',
        ],
        blocks: [
            'sw_bulk_edit_product_media',
            'sw_bulk_edit_product_media_form',
            'sw_bulk_edit_product_media_modal',
        ],
        computed: [
            'product',
            'productMediaRepository',
            'mediaDefaultFolderRepository',
            'mediaDefaultFolderCriteria',
        ],
        methods: [
            'createdComponent',
            'loadMediaDefaultFolder',
            'getMediaDefaultFolderId',
            'onAddMedia',
            'addMedia',
            'isExistingMedia',
        ],
    },
});
