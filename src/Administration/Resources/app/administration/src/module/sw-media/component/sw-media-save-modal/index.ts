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
    name: 'sw-media-save-modal',
    load: () => import('./src/sw-media-save-modal'),
    public: {
        props: [
            'initialFolderId',
            'initialFileName',
            'fileType',
        ],
        emits: [
            'save-media',
            'modal-close',
        ],
        blocks: [
            'sw_media_modal_v2',
            'sw_media_save_modal_content',
            'sw_media_save_modal_navigation_and_search',
            'sw_media_save_modal_folder_breadcrumbs',
            'sw_media_save_modal_media_library',
            'sw_media_save_modal_modal_footer',
            'sw_media_save_modal_input_file_name',
            'sw_media_save_modal_button_cancel',
            'sw_media_save_modal_button_save',
        ],
        computed: [
            'mediaFolderRepository',
            'mediaRepository',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeDestroyComponent',
            'addResizeListener',
            'removeOnResizeListener',
            'getComponentWidth',
            'fetchCurrentFolder',
            'getMediaEntityForUpload',
            'onSaveMedia',
            'onEmitModalClosed',
        ],
    },
});
