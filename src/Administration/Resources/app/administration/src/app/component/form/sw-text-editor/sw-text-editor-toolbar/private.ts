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
    name: 'sw-text-editor-toolbar',
    load: () => import('./src/sw-text-editor-toolbar'),
    public: {
        props: [
            'parentIsActive',
            'isInlineEdit',
            'selection',
            'buttonConfig',
            'isCodeEdit',
            'isTableEdit',
        ],
        emits: [
            'created-el',
            'destroyed-el',
            'remove-link',
            'text-style-change',
            'table-edit',
            'on-set-link',
        ],
        slots: [
            'itemsSlot',
        ],
        blocks: [
            'sw_text_editor_toolbar',
            'sw_text_editor_toolbar_buttons',
            'sw_text_editor_toolbar_buttons_left',
            'sw_text_editor_toolbar_buttons_middle',
            'sw_text_editor_toolbar_buttons_right',
            'sw_text_editor_toolbar_slot',
        ],
        computed: [
            'classes',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'isOverlayingLeft',
            'destroyedComponent',
            'onMouseUp',
            'setToolbarPosition',
            'setSelectionRange',
            'setButtonValues',
            'isDisabled',
            'handleToolbarClick',
            'onButtonClick',
            'closeExpandedMenu',
            'setActiveTags',
            'setButtonPositions',
            'handleTextStyleChangeLink',
            'keepSelection',
            'onToggleMenu',
        ],
    },
});
