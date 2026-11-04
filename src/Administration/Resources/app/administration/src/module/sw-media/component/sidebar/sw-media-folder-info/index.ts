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
    name: 'sw-media-folder-info',
    load: () => import('./src/sw-media-folder-info'),
    public: {
        props: [
            'mediaFolder',
            'editable',
        ],
        emits: [
            'media-folder-renamed',
        ],
        blocks: [
            'sw_media_folder_info',
            'sw_media_quickinfo_folder_quickactions',
            'sw_media_quickinfo_folder_quickactions_content',
            'sw_media_quickinfo_folder_quickactions_move',
            'sw_media_quickinfo_folder_quickactions_settings',
            'sw_media_quickinfo_folder_quickactions_dissolve',
            'sw_media_quickinfo_folder_quickactions_delete',
            'sw_media_quickinfo_folder_metadata',
            'sw_media_quickinfo_folder_metadata_content',
            'sw_media_quickinfo_folder_metadata_content_base',
            'sw_media_folder_info_settings_modal',
            'sw_media_folder_info_dissolve_modal',
            'sw_media_folder_info_move_modal',
            'sw_media_folder_info_modal_delete',
        ],
        computed: [
            'mediaFolderRepository',
            'createdAt',
            'nameItemClasses',
        ],
        methods: [
            'onChangeFolderName',
            'quickActionClasses',
        ],
    },
});
