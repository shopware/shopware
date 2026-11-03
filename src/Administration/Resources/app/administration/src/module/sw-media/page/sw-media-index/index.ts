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
    name: 'sw-media-index',
    load: () => import('./src/sw-media-index'),
    public: {
        props: [
            'routeFolderId',
            'fileAccept',
        ],
        blocks: [
            'sw_media_index',
            'sw_media_list_search_bar',
            'sw_media_index_smart_bar',
            'sw_media_index_bread_crumb_navigation',
            'sw_media_index_bread_crumb_navigation_icon',
            'sw_media_index_bread_crumb_navigation_parent',
            'sw_media_index_smart_bar_header',
            'sw_media_index_smart_bar_heading',
            'sw_media_index_smart_bar_media_upload',
            'sw_media_index_language_switch',
            'sw_media_index_page_content',
            'sw_media_index_listing_grid',
            'sw_media_index_sidebar',
            'sw_media_index_list_grid_loader',
        ],
        computed: [
            'mediaFolderRepository',
            'mediaRepository',
            'rootFolder',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'updateFolder',
            'destroyedComponent',
            'onUploadsAdded',
            'onUploadFinished',
            'onUploadFailed',
            'onUploadCanceled',
            'onChangeLanguage',
            'onSearch',
            'onItemsDeleted',
            'onMediaFoldersDissolved',
            'reloadList',
            'decrementPendingUploads',
            'clearSelection',
            'onMediaUnselect',
            'updateRoute',
        ],
    },
});
