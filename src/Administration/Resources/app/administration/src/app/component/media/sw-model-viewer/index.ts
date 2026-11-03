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
    name: 'sw-model-viewer',
    load: () => import('./src/sw-model-viewer'),
    public: {
        props: [
            'source',
        ],
        blocks: [
            'sw_model_viewer',
        ],
        computed: [
            'mediaRepository',
        ],
        methods: [
            'createdComponent',
            'beforeUnmountedComponent',
            'mountedComponent',
            'initializeQuickView',
            'disposeQuickView',
            'onMediaLibraryItemUpdated',
        ],
    },
});
