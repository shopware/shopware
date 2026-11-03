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
    name: 'sw-select-option',
    load: () => import('./src/sw-select-option'),
    public: {
        props: [
            'index',
            'item',
            'disabled',
            'selected',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_single_select_option',
            'sw_single_select_option_item_text_holder',
            'sw_single_select_option_item_text',
            'sw_single_select_option_item_icon_transition',
            'sw_single_select_option_item_icon',
        ],
        computed: [
            'componentClasses',
        ],
        methods: [
            'createdComponent',
            'destroyedComponent',
            'registerEvents',
            'removeEvents',
            'emitActiveResultPosition',
            'onClicked',
            'checkActiveState',
            'selectOptionOnEnter',
            'isInSelections',
            'onMouseEnter',
        ],
    },
});
