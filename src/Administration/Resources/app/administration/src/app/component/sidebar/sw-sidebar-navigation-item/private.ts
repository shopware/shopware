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
    name: 'sw-sidebar-navigation-item',
    load: () => import('./src/sw-sidebar-navigation-item'),
    public: {
        props: [
            'sidebarItem',
        ],
        emits: [
            'item-click',
        ],
        blocks: [
            'sw_sidebar_navigation_item',
            'sw_sidebar_navigation_item_content',
        ],
        computed: [
            'badgeTypeClasses',
            'tooltipContent',
        ],
        methods: [
            'emitButtonClicked',
        ],
    },
});
