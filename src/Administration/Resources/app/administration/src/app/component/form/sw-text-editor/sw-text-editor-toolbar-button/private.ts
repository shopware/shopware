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
    name: 'sw-text-editor-toolbar-button',
    load: () => import('./src/sw-text-editor-toolbar-button'),
    public: {
        props: [
            'buttonConfig',
            'disabled',
            'isInlineEdit',
        ],
        emits: [
            'button-click',
            'menu-toggle',
        ],
        slots: [
            'buttonSlot',
        ],
        blocks: [
            'sw_text_editor_toolbar_button',
            'sw_text_editor_toolbar_button_icon',
            'sw_text_editor_toolbar_button_name',
            'sw_text_editor_toolbar_button_color',
            'sw_text_editor_toolbar_button_child',
            'sw_text_editor_toolbar_button_child_item',
            'sw_text_editor_toolbar_button_link_menu',
            'sw_text_editor_toolbar_button_table',
            'sw_text_editor_toolbar_button_table_menu_content',
            'sw_text_editor_toolbar_button_slot',
        ],
        computed: [
            'classes',
            'tooltipAppearance',
        ],
        methods: [
            'mountedComponent',
            'buttonHandler',
            'childActive',
            'handleButtonClick',
            'onToggleMenu',
            'getDropdownClasses',
            'onChildMounted',
            'getTooltipConfig',
            'positionLinkMenu',
        ],
    },
});
