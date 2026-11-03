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
    name: 'sw-sidebar-media-item',
    load: () => import('./src/sw-sidebar-media-item'),
    public: {
        props: [
            'initialFolderId',
            'isParentLoading',
            'disabled',
        ],
        slots: [
            'context-menu-items',
        ],
        blocks: [
            'sw_sidebar_media_item',
            'sw_sidebar_media_item_content',
            'sw_sidebar_media_item_search_field',
            'sw_sidebar_media_item_folder_navigation',
            'sw_sidebar_media_item_media_item_list',
            'sw_sidebar_media_item_media_item_list_folder_item',
            'sw_sidebar_media_item_media_item_list_media_item',
            'sw_sidebar_media_item_context_items',
            'sw_sidebar_media_item_load_more_button',
            'sw_sidebar_media_item_loader',
        ],
        computed: [
            'mediaRepository',
            'mediaFolderRepository',
            'showMore',
            'itemsLoaded',
        ],
        methods: [
            'createdComponent',
            'onSearchTermChange',
            'debouncedSearch',
            'initializeContent',
            'getSubFolders',
            'handleFolderGridItemDelete',
            'handleMediaGridItemDelete',
            'onLoadMore',
            'extendList',
            'getList',
            'getListingCriteria',
            'openContent',
            'onNavigateToFolder',
        ],
    },
});
