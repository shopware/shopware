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
    name: 'sw-media-modal-folder-dissolve',
    load: () => import('./src/sw-media-modal-folder-dissolve'),
    public: {
        props: [
            'itemsToDissolve',
        ],
        emits: [
            'media-folder-dissolve-modal-close',
            'media-folder-dissolve-modal-dissolve',
        ],
        blocks: [
            'sw_media_modal_folder_dissolve',
            'sw_media_modal_folder_dissolve_body',
            'sw_media_modal_folder_dissolve_footer',
            'sw_media_modal_folder_dissolve__cancel_button',
            'sw_media_modal_folder_dissolve_confirm_button',
        ],
        methods: [
            'closeDissolveModal',
            '_dissolveSelection',
            'dissolveSelection',
        ],
    },
});
