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
    name: 'sw-media-folder-content',
    load: () => import('./src/sw-media-folder-content'),
    public: {
        props: [
            'startFolderId',
            'selectedId',
        ],
        emits: [
            'selected',
        ],
        blocks: [
            'sw_media_folder_content',
            'sw_media_folder_content_folder_listing',
            'sw_media_folder_content_list_item',
            'sw_media_folder_content_folder_icon',
            'sw_media_folder_content_button_folder',
            'sw_media_folder_content_button_folder_button',
            'sw_media_folder_content_folder_button_icon',
            'sw_media_folder_content_switch_button',
        ],
        computed: [
            'mediaFolderRepository',
            'assetFilter',
        ],
        methods: [
            'mountedComponent',
            'getSubFolders',
            'getChildCount',
            'fetchParentFolder',
            'updateParentFolder',
            'emitInput',
        ],
    },
});
