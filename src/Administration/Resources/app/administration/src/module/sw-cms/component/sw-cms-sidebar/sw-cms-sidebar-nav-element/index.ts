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
    name: 'sw-cms-sidebar-nav-element',
    load: () => import('./src/sw-cms-sidebar-nav-element'),
    public: {
        props: [
            'block',
            'removable',
            'duplicable',
        ],
        emits: [
            'block-duplicate',
            'block-delete',
        ],
        blocks: [
            'sw_cms_sidebar_navigator_element',
            'sw_cms_sidebar_sidebar_navigator_element_label',
            'sw_cms_sidebar_sidebar_navigator_element_duplicate',
            'sw_cms_sidebar_sidebar_navigator_element_delete',
        ],
        methods: [
            'onBlockDuplicate',
            'onBlockDelete',
        ],
    },
});
