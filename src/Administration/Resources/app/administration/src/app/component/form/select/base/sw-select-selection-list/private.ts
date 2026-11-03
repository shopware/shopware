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
    name: 'sw-select-selection-list',
    load: () => import('./src/sw-select-selection-list'),
    public: {
        props: [
            'selections',
            'labelProperty',
            'valueProperty',
            'enableSearch',
            'invisibleCount',
            'size',
            'alwaysShowPlaceholder',
            'placeholder',
            'isLoading',
            'searchTerm',
            'disabled',
            'selectionDisablingMethod',
            'hideLabels',
            'inputLabel',
            'autocomplete',
        ],
        emits: [
            'total-count-click',
            'search-term-change',
            'last-item-delete',
            'key-down-enter',
            'item-remove',
        ],
        slots: [
            'selected-option',
            'label-property',
            'invisible-count',
            'input',
        ],
        blocks: [
            'sw_select_selection_list',
            'sw_select_selection_list_item',
            'sw_select_selection_list_item_inner',
            'sw_select_selection_list_item_text',
            'sw_select_selection_list_load_more',
            'sw_select_selection_list_load_more_item_button',
            'sw_select_selection_list_input',
        ],
        computed: [
            'showPlaceholder',
        ],
        methods: [
            'isSelectionDisabled',
            'onClickInvisibleCount',
            'onSearchTermChange',
            'onKeyDownDelete',
            'onKeyDownEnter',
            'onClickDismiss',
            'focus',
            'blur',
            'select',
            'getFocusEl',
        ],
    },
});
