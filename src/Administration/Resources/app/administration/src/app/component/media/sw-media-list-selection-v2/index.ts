import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-media-list-selection-v2',
    load: () => import('./src/sw-media-list-selection-v2'),
    public: {
        props: [
            'entity',
            'entityMediaItems',
            'uploadTag',
            'defaultFolderName',
            'disabled',
        ],
        emits: [
            'open-sidebar',
            'upload-finish',
            'item-sort',
            'item-remove',
        ],
        blocks: [
            'sw_media_list_selection_v2',
            'sw_media_list_selection_v2_upload',
            'sw_media_list_selection_v2_grid',
            'sw_media_list_selection_v2_grid_items',
        ],
        computed: [
            'mediaRepository',
            'currentCount',
            'mediaItems',
            'gridAutoRows',
            'uploadId',
            'defaultFolder',
        ],
        methods: [
            'mountedComponent',
            'updateColumnCount',
            'createPlaceholders',
            'onUploadsAdded',
            'onMediaUploadButtonOpenSidebar',
            'successfulUpload',
            'onUploadFailed',
            'onMediaItemDragSort',
            'onDeboundDragDrop',
            'removeItem',
        ],
    },
});
