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
    name: 'sw-media-sidebar',
    load: () => import('./src/sw-media-sidebar'),
    public: {
        props: [
            'items',
            'currentFolderId',
            'editable',
        ],
        emits: [
            'media-sidebar-folder-renamed',
        ],
        blocks: [
            'sw_media_sidebar',
            'sw_media_sidebar_headline',
            'sw_media_sidebar_item_quickinfo_content',
        ],
        computed: [
            'mediaRepository',
            'mediaFolderRepository',
            'mediaNameFilter',
            'mediaSidebarClasses',
            'isSingleFile',
            'isMultipleFile',
            'headLine',
            'getSelectedFilesCount',
            'firstEntity',
            'assetFilter',
            'filteredAttributes',
        ],
        methods: [
            'createdComponent',
            'fetchCurrentFolder',
            'onMediaFolderRenamed',
            'onFirstItemUpdated',
        ],
    },
});
