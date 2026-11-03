import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-media-grid',
    load: () => import('./src/sw-media-grid'),
    public: {
        props: [
            'presentation',
        ],
        emits: [
            'media-grid-selection-clear',
        ],
        slots: [
            'content',
            'default',
        ],
        blocks: [
            'sw_media_grid',
            'sw_media_grid_slot_content',
            'sw_media_grid_default_slot',
        ],
        computed: [
            'mediaColumnDefinitions',
            'presentationClass',
            'nonDeselectingComponents',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'clearSelectionOnClickOutside',
            'originatesFromExcludedComponent',
            'isEmittedFromChildren',
            'emitSelectionCleared',
        ],
    },
});
