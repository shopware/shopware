import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package innovation
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-model-editor',
    load: () => import('./src/sw-model-editor'),
    public: {
        props: [
            'source',
        ],
        blocks: [
            'sw_model_editor',
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
            'setGizmoMode',
            'onObjectChange',
            'changeModelPosition',
            'changeModelRotation',
            'changeModelScale',
            'changeModelScaleLinked',
            'save',
            'saveInitialProperties',
            'compareInitialProperties',
            'syncProperties',
        ],
    },
});
