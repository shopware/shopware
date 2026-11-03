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
    name: 'sw-media-display-options',
    load: () => import('./src/sw-media-display-options'),
    public: {
        props: [
            'presentation',
            'sorting',
            'hidePresentation',
            'disabled',
        ],
        emits: [
            'media-sorting-change',
            'media-presentation-change',
        ],
        blocks: [
            'sw_media_display_options',
            'sw_media_display_options_presentation',
            'sw_media_display_options_sorting',
        ],
        computed: [
            'sortingConCat',
            'sortOptions',
            'previewOptions',
            'presentationOptions',
            'sortOptionsSelect',
        ],
        methods: [
            'onSortingChanged',
            'onPresentationChanged',
        ],
    },
});
