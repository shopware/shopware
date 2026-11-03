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
    name: 'sw-media-modal-move',
    load: () => import('./src/sw-media-modal-move'),
    public: {
        props: [
            'itemsToMove',
        ],
        emits: [
            'media-move-modal-close',
            'media-move-modal-items-move',
        ],
        blocks: [
            'sw_media_modal_move',
            'sw_media_modal_body',
            'sw_media_modal_move_breadcrumbs',
            'sw_media_modal_footer',
            'sw_media_modal_move_cancel_button',
            'sw_media_modal_move_confirm_button',
        ],
        computed: [
            'mediaRepository',
            'mediaFolderRepository',
            'mediaNameFilter',
            'targetFolderId',
            'rootFolderName',
            'isMoveDisabled',
            'startFolderId',
            'assetFilter',
        ],
        methods: [
            'mountedComponent',
            'closeMoveModal',
            'isNotPartOfItemsToMove',
            'updateParentFolder',
            'fetchParentFolder',
            'onSelection',
            '_moveSelection',
            'moveSelection',
        ],
    },
});
