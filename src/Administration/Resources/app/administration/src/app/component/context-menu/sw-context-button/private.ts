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
    name: 'sw-context-button',
    load: () => import('./src/sw-context-button'),
    public: {
        props: [
            'showMenuOnStartup',
            'menuWidth',
            'menuHorizontalAlign',
            'menuVerticalAlign',
            'icon',
            'iconSize',
            'disabled',
            'autoClose',
            'autoCloseOutsideClick',
            'additionalContextMenuClasses',
            'zIndex',
            'ariaLabel',
        ],
        emits: [
            'on-open-change',
        ],
        slots: [
            'button',
            'default',
        ],
        blocks: [
            'sw_context_button',
            'sw_context_button_button',
            'sw_context_button_menu',
            'sw_context_button_menu_slot_default',
        ],
        computed: [
            'menuStyles',
            'contextClass',
            'contextButtonClass',
            'contextMenuClass',
        ],
        methods: [
            'beforeUnmountComponent',
            'onClickButton',
            'openMenu',
            'handleOutsideClickEvent',
            'handleClickEvent',
            'closeMenu',
            'removeClickEventListeners',
        ],
    },
});
