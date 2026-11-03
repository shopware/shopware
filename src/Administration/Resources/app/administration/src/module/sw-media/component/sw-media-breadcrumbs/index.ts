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
    name: 'sw-media-breadcrumbs',
    load: () => import('./src/sw-media-breadcrumbs'),
    public: {
        props: [
            'currentFolderId',
            'small',
            'disabled',
        ],
        emits: [
            'update:currentFolderId',
        ],
        blocks: [
            'sw_media_breadcrumbs',
            'sw_media_breadcrumbs_root_folder',
            'sw_media_breadcrumbs_parent_folder',
            'sw_media_breadcrumbs_current_folder',
        ],
        computed: [
            'mediaFolderRepository',
            'rootFolder',
            'swMediaBreadcrumbsClasses',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'updateFolder',
            'onBreadcrumbsItemClicked',
        ],
    },
});
