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
    name: 'sw-sidebar-item',
    load: () => import('./src/sw-sidebar-item'),
    public: {
        props: [
            'title',
            'icon',
            'disabled',
            'position',
            'badge',
            'hasSimpleBadge',
            'badgeType',
            'tooltipShortcut',
        ],
        emits: [
            'toggle-active',
            'close-content',
            'click',
        ],
        slots: [
            'headline-content',
            'default',
        ],
        blocks: [
            'sw_sidebar_item',
            'sw_sidebar_item_headline',
            'sw_sidebar_item_title',
            'sw_sidebar_item_headline_slot',
            'sw_sidebar_item_close_button',
            'sw_sidebar_item_content',
            'sw_sidebar_item_default_slot',
        ],
        computed: [
            'sidebarItemClasses',
            'hasDefaultSlot',
            'showContent',
        ],
        methods: [
            'createdComponent',
            'registerToggleActiveListener',
            'registerCloseContentListener',
            'openContent',
            'closeContent',
            'sidebarButtonClick',
        ],
    },
});
