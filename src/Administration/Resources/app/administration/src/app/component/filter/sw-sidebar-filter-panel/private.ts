import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-sidebar-filter-panel',
    load: () => import('./src/sw-sidebar-filter-panel'),
    public: {
        props: [
            'activeFilterNumber',
        ],
        blocks: [
            'sw_sidebar_filter_panel',
            'sw_sidebar_filter_panel_headline',
            'sw_sidebar_filter_panel_content',
        ],
        methods: [
            'registerSidebarItem',
            'openFilterPanel',
            'resetAll',
        ],
    },
});
