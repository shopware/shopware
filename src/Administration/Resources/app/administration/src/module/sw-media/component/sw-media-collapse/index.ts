import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * A section of the media sidebar that the merchant opens and closes by its title.
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-collapse,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-media-collapse',
    extends: 'sw-collapse',
    load: () => import('./src/sw-media-collapse'),
    public: {
        props: [
            'title',
            'expandOnLoading',
        ],
        slots: [
            'header',
            'content',
        ],
        blocks: [
            'sw_collapse_panel_header_slot',
            'sw_media_collapse_title',
            'sw_media_collapse_buttons',
            'sw_collapse_panel',
            'sw_collapse_panel_header',
            'sw_collapse_panel_content',
            'sw_collapse_panel_content_slot',
        ],
        computed: [
            'expandButtonClass',
            'collapseButtonClass',
        ],
        methods: [
            'collapseItem',
        ],
    },
});
