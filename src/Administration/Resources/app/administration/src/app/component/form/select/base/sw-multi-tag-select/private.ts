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
    name: 'sw-multi-tag-select',
    load: () => import('./src/sw-multi-tag-select'),
    public: {
        props: [
            'value',
            'valueLimit',
            'placeholder',
            'isLoading',
            'validMessage',
            'invalidMessage',
            'validate',
            'disabled',
            'autocomplete',
        ],
        emits: [
            'add-item-is-valid',
            'update:value',
            'display-values-expand',
        ],
        slots: [
            'selection-label-property',
            'message-add-data',
            'message-enter-valid-data',
            'label',
            'hint',
        ],
        blocks: [
            'sw_multi_tag_select',
            'sw_multi_tag_select_base',
            'sw_multi_tag_select_base_selection',
            'sw_multi_tag_select_base_selection_slot',
            'sw_multi_tag_select_base_selection_list',
            'sw_multi_tag_select_base_selection_list_label',
            'sw_multi_tag_select_base_selection_list_label_inner',
            'sw_multi_tag_select_validation',
            'sw_multi_tag_select_validation_valid',
            'sw_multi_tag_select_validation_valid_message',
            'sw_multi_tag_select_validation_invalid',
            'sw_multi_tag_select_validation_invalid_message',
        ],
        computed: [
            'errorObject',
            'inputIsValid',
            'visibleValues',
            'totalValuesCount',
            'invisibleValueCount',
        ],
        methods: [
            'onSelectionListKeyDownEnter',
            'addItem',
            'remove',
            'removeLastItem',
            'onSearchTermChange',
            'getKey',
            'setDropDown',
            'expandValueLimit',
        ],
    },
});
