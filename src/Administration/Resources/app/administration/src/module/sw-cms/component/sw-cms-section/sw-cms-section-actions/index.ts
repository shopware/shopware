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
    name: 'sw-cms-section-actions',
    load: () => import('./src/sw-cms-section-actions'),
    public: {
        props: [
            'section',
            'disabled',
        ],
        blocks: [
            'sw_cms_section_actions',
            'sw_cms_section_action_select',
        ],
        computed: [
            'componentClasses',
            'cmsPageStateStore',
        ],
        methods: [
            'selectSection',
        ],
    },
});
