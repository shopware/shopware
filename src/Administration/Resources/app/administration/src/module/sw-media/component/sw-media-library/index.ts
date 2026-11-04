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
    name: 'sw-media-library',
    load: () => import('./src/sw-media-library'),
    public: {
        props: [
            'selection',
            'folderId',
            'pendingUploads',
            'limit',
            'term',
            'compact',
            'editable',
            'allowMultiSelect',
            'allowCreateFolder',
            'disabled',
        ],
        emits: [
            'update:selection',
            'media-folder-change',
        ],
        blocks: [
            'sw_media_library',
            'sw_media_library_display_options',
            'sw_media_index_create_folder',
            'sw_media_library_scroll_container',
            'sw_media_library_media_grid',
            'sw_media_library_back_to_parent_item',
            'sw_media_library_media_item_list',
            'sw_media_library_load_buttons',
            'sw_media_library_load_more_button',
            'sw_media_library_load_all_button',
            'sw_media_library_empty_state',
        ],
        computed: [
            'shouldDisplayEmptyState',
            'mediaRepository',
            'mediaFolderRepository',
            'mediaFolderConfigurationRepository',
            'selectableItems',
            'rootFolder',
            'gridPresentation',
            'showItemsAsList',
            'allLoaded',
            'loadMoreLoadsEverything',
            'showLoadMoreButton',
            'showLoadAllButton',
            'nextMediaCriteria',
            'nextFoldersCriteria',
            'assetFilter',
            'adminEsEnable',
        ],
        methods: [
            'createdComponent',
            'beforeUnmountedComponent',
            'refreshList',
            'isValidTerm',
            'loadNextItems',
            'loadAll',
            'isLoaderDone',
            'loadItems',
            'nextMedia',
            'nextFolders',
            'fetchAssociatedFolders',
            'goToParentFolder',
            'clearSelection',
            'injectItem',
            'injectMedia',
            'createFolder',
            'removeNewFolder',
            'refreshItem',
        ],
    },
});
