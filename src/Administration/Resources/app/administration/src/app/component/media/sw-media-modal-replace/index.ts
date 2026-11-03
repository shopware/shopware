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
    name: 'sw-media-modal-replace',
    load: () => import('./src/sw-media-modal-replace'),
    public: {
        props: [
            'itemToReplace',
        ],
        emits: [
            'media-replace-modal-close',
            'media-replace-modal-item-replaced',
        ],
        blocks: [
            'sw_media_modal_replace',
            'sw_media_modal_replace_modal_footer',
            'sw_media_modal_replace_cancel_button',
            'sw_media_modal_replace_replace_button',
        ],
        computed: [
            'presignedSupported',
        ],
        methods: [
            'onNewUpload',
            'emitCloseReplaceModal',
            'replaceMediaItem',
            'runPresignedReplace',
        ],
    },
});
