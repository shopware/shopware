import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-single-select,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-grouped-single-select',
    extends: 'sw-single-select',
    load: () => import('./src/sw-grouped-single-select'),
    public: {
        props: [
            'groups',
            'groupIdProperty',
            'options',
            'value',
            'isLoading',
            'highlightSearchTerm',
            'placeholder',
            'labelProperty',
            'valueProperty',
            'popoverClasses',
            'searchFunction',
            'disableSearchFunction',
            'label',
            'autocomplete',
        ],
        emits: [
            'update:value',
            'item-selected',
            'on-open-change',
            'before-selection-clear',
            'search',
            'paginate',
        ],
        slots: [
            'result-group',
            'result-item',
            'result-label-property',
            'selection-label-property',
            'before-item-list',
            'after-item-list',
            'label',
            'hint',
        ],
        blocks: [
            'sw_single_select_base_results_list_result',
            'sw_single_select_base_results_list_result_label',
            'sw_single_select',
            'sw_single_select_base',
            'sw_single_select_base_selection',
            'sw_single_select_base_selection_slot',
            'sw_single_select_single_selection_inner',
            'sw_single_select_single_selection_inner_label',
            'sw_single_select_single_selection_inner_input',
            'sw_single_select_base_results',
            'sw_single_select_base_results_slot',
            'sw_single_select_base_results_list',
            'sw_single_select_base_results_list_before',
            'sw_single_select_base_results_list_after',
        ],
        computed: [
            'currentValue',
            'inputClasses',
            'selectionTextClasses',
            'singleSelection',
            'visibleResults',
        ],
        methods: [
            'getGroupClasses',
            'getGroupLabel',
            'shouldShowGroupTitle',
            'isSelected',
            'onSelectExpanded',
            'tryGetSearchText',
            'onSelectCollapsed',
            'closeResultList',
            'setValue',
            'resetActiveItem',
            'onInputSearchTerm',
            'debouncedSearch',
            'search',
            'getKey',
            'clearSelection',
        ],
    },
});
