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
    name: 'sw-context-menu-item',
    load: () => import('./src/sw-context-menu-item'),
    public: {
        props: [
            'icon',
            'disabled',
            'routerLink',
            'target',
            'variant',
        ],
        slots: [
            'icon',
            'default',
        ],
        blocks: [
            'sw_context_menu_item',
            'sw_context_menu_item_router_link',
            'sw_context_menu_item_icon',
            'sw_context_menu_item_slot_icon',
            'sw_context_menu_item_text',
            'sw_context_menu_item_slot_default',
            'sw_context_menu_item_entry',
            'sw_context_menu_item_entry_icon',
            'sw_context_menu_item_entry_slot_icon',
            'sw_context_menu_item_entry_text',
            'sw_context_menu_item_entry_slot_default',
        ],
        computed: [
            'contextMenuItemStyles',
        ],
        methods: [
            'handleClick',
        ],
    },
});
