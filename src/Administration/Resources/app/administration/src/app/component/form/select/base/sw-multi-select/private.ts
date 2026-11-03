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
    name: 'sw-multi-select',
    load: () => import('./src/sw-multi-select'),
    public: {
        props: [
            'options',
            'value',
            'labelProperty',
            'valueProperty',
            'placeholder',
            'valueLimit',
            'isLoading',
            'highlightSearchTerm',
            'searchFunction',
            'label',
            'autocomplete',
        ],
        emits: [
            'update:value',
            'item-add',
            'item-remove',
            'search-term-change',
            'display-values-expand',
            'paginate',
        ],
        slots: [
            'selection-label-property',
            'before-item-list',
            'result-item',
            'result-label-property',
            'after-item-list',
            'label',
            'hint',
        ],
        blocks: [
            'sw_multi_select',
            'sw_multi_select_base',
            'sw_multi_select_base_selection',
            'sw_multi_select_base_selection_slot',
            'sw_multi_select_base_selection_list',
            'sw_multi_select_base_selection_list_label',
            'sw_multi_select_base_selection_list_label_inner',
            'sw_multi_select_base_results',
            'sw_multi_select_base_results_slot',
            'sw_multi_select_base_results_list',
            'sw_multi_select_base_results_list_before',
            'sw_multi_select_base_results_list_result',
            'sw_multi_select_base_results_list_result_label',
            'sw_multi_select_base_results_list_after',
        ],
        computed: [
            'visibleValues',
            'totalValuesCount',
            'invisibleValueCount',
            'currentValue',
            'visibleResults',
        ],
        methods: [
            'isSelected',
            'addItem',
            'remove',
            'removeLastItem',
            'expandValueLimit',
            'onSearchTermChange',
            'resetActiveItem',
            'onSelectExpanded',
            'onSelectCollapsed',
            'getKey',
        ],
    },
});
