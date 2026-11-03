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
    name: 'sw-media-modal-delete',
    load: () => import('./src/sw-media-modal-delete'),
    public: {
        props: [
            'itemsToDelete',
        ],
        emits: [
            'media-delete-modal-close',
            'media-delete-modal-items-delete',
        ],
        blocks: [
            'sw_media_modal_delete',
            'sw_media_modal_body',
            'sw_media_modal_footer',
            'sw_media_modal_delete_cancel_button',
            'sw_media_modal_delete_confirm_button',
        ],
        computed: [
            'mediaRepository',
            'mediaFolderRepository',
            'mediaNameFilter',
            'snippets',
            'mediaQuickInfo',
            'mediaInUsages',
        ],
        methods: [
            'createdComponent',
            'closeDeleteModal',
            'getEntityRepository',
            '_deleteSelection',
            'deleteSelection',
            'updateSuccessNotification',
            '_checkInUsage',
        ],
    },
});
