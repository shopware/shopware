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
    name: 'sw-media-quickinfo-multiple',
    load: () => import('./src/sw-media-quickinfo-multiple'),
    public: {
        props: [
            'items',
            'editable',
        ],
        emits: [
            'media-item-selection-remove',
            'media-item-selection-clear',
        ],
        blocks: [
            'sw_media_quickinfo_multiple',
            'sw_media_quickinfo_multiple_quickactions',
            'sw_media_quickinfo_multiple_quickactions_content',
            'sw_media_quickinfo_multiple_quickactions_move',
            'sw_media_quickinfo_multiple_quickactions_delete',
            'sw_media_quickinfo_folder_quickactions_dissolve',
            'sw_media_quickinfo_multiple_quickactions_custom_action',
            'sw_media_quickinfo_multiple_file_names',
            'sw_media_quickinfo_multiple_file_names_content',
            'sw_media_sidebar_modal_delete',
            'sw_media_sidebar_folder_dissolve_modal',
            'sw_media_sidebar_folder_move_modal',
        ],
        computed: [
            'itemsIsAvailable',
            'getFileSize',
            'getFileSizeLabel',
            'hasFolder',
            'hasMedia',
            'mediaItems',
            'extensionSdkButtons',
            'isPrivate',
        ],
        methods: [
            'onRemoveItemFromSelection',
            'onClearSelection',
            'matchesFileTypes',
            'runAppAction',
            'quickActionClassesDelete',
            'quickActionClasses',
        ],
    },
});
