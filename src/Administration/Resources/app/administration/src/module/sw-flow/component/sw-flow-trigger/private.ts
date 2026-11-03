import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-trigger',
    load: () => import('./src/sw-flow-trigger'),
    public: {
        props: [
            'overlay',
            'disabled',
            'eventName',
            'isUnknownTrigger',
        ],
        emits: [
            'option-select',
        ],
        blocks: [
            'sw_flow_trigger',
            'sw_flow_trigger_select_field',
            'sw_flow_trigger_select_field_input',
            'sw_flow_trigger_loader',
            'sw_flow_trigger_dropdown_icon',
            'sw_flow_trigger_select_transition',
            'sw_flow_trigger_event_selection',
            'sw_flow_trigger_container',
            'sw_flow_trigger_select_tree',
            'sw_flow_trigger_tree_headline',
            'sw_flow_trigger_tree_search',
            'sw_flow_trigger_tree_item_actions',
            'sw_flow_trigger_search_list',
            'sw_flow_trigger_search_list_item',
            'sw_flow_trigger_search_list_item_icon',
            'sw_flow_trigger_search_list_item_name',
            'sw_flow_trigger_search_list_item_name_highlight',
            'sw_flow_trigger_search_empty',
            'sw_flow_event_change_confirm_modal',
        ],
        computed: [
            'swFlowTriggerClasses',
            'formatEventName',
            'showTreeView',
            'eventTree',
            'isTemplate',
            'triggerNamePlaceholder',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'handleClickEvent',
            'handleGeneralKeyEvents',
            'handleArrowKeyEvents',
            'getClosestSiblingAncestor',
            'getClosestSiblingDescendant',
            'getFirstChildById',
            'getSibling',
            'changeSearchSelection',
            'toggleSelectedTreeItem',
            'findTreeItemVNodeById',
            'openDropdown',
            'closeDropdown',
            'changeTrigger',
            'onConfirm',
            'onCloseConfirm',
            'getLastEventName',
            'getDataByEvent',
            'hasOnlyStopFlow',
            'getEventTree',
            'getBreadcrumb',
            'onClickSearchItem',
            'getEventName',
            'isSearchResultInFocus',
            'getEventNameTranslated',
        ],
    },
});
