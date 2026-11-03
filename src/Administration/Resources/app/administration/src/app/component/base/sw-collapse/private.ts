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
    name: 'sw-collapse',
    load: () => import('./src/sw-collapse'),
    public: {
        props: [
            'expandOnLoading',
        ],
        slots: [
            'header',
            'content',
        ],
        blocks: [
            'sw_collapse_panel',
            'sw_collapse_panel_header',
            'sw_collapse_panel_header_slot',
            'sw_collapse_panel_content',
            'sw_collapse_panel_content_slot',
        ],
        methods: [
            'collapseItem',
        ],
    },
});
