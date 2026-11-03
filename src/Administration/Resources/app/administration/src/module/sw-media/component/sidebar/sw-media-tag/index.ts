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
    name: 'sw-media-tag',
    load: () => import('./src/sw-media-tag'),
    public: {
        props: [
            'media',
            'disabled',
        ],
        blocks: [
            'sw_media_tag',
            'sw_media_tag_input',
        ],
        computed: [
            'mediaRepository',
        ],
        methods: [
            'handleChange',
        ],
    },
});
