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
    name: 'sw-select-result',
    load: () => import('./src/sw-select-result'),
    public: {
        props: [
            'index',
            'item',
            'disabled',
            'selected',
            'descriptionPosition',
            'ariaLabel',
        ],
        slots: [
            'preview',
            'default',
            'description',
        ],
        blocks: [
            'sw_select_result',
            'sw_select_result_item_preview',
            'sw_select_result_item_text_holder',
            'sw_select_result_item_text',
            'sw_select_result_item_icon_transition',
            'sw_select_result_item_icon',
            'sw_select_result_item_description_holder',
            'sw_select_result_item_description',
        ],
        computed: [
            'resultClasses',
            'hasDescriptionSlot',
        ],
        methods: [
            'createdComponent',
            'destroyedComponent',
            'checkIfSelected',
            'checkIfActive',
            'onClickResult',
            'onMouseEnter',
        ],
    },
});
