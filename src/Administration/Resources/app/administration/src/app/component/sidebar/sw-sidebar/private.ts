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
    name: 'sw-sidebar',
    load: () => import('./src/sw-sidebar'),
    public: {
        props: [
            'propagateWidth',
        ],
        emits: [
            'item-click',
            'item-register',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_sidebar',
            'sw_sidebar_navigation',
            'sw_sidebar_navigation_list',
            'sw_sidebar_navigation_item',
            'sw_sidebar_content',
        ],
        computed: [
            'sections',
            'sidebarClasses',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'destroyedComponent',
            '_isItemRegistered',
            '_isAnyItemActive',
            'closeSidebar',
            'onResize',
            'updateSidebarOffset',
            'registerSidebarItem',
            'setItemActive',
        ],
    },
});
