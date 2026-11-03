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
    name: 'sw-flow-list',
    load: () => import('./src/sw-flow-list'),
    public: {
        props: [
            'searchTerm',
        ],
        emits: [
            'on-update-total',
        ],
        blocks: [
            'sw_flow_list',
            'sw_flow_list_search_bar',
            'sw_flow_list_smart_bar_header',
            'sw_flow_list_smart_bar_actions',
            'sw_flow_list_content',
            'sw_flow_list_grid_columns',
            'sw_flow_list_grid_columns_active',
            'sw_flow_list_columns_active_label',
            'sw_flow_list_grid_columns_event_name',
            'sw_flow_list_grid_actions',
            'sw_flow_list_grid_actions_duplicate',
            'sw_flow_list_grid_actions_edit',
            'sw_flow_list_grid_actions_custom',
            'sw_flow_list_grid_actions_delete',
            'sw_flow_list_grid_action_modal',
            'sw_flow_list_grid_action_modal_confirm_delete_text',
            'sw_flow_list_grid_action_modal_buttons',
            'sw_flow_list_grid_bulk_modal_delete_confirm_text',
            'sw_flow_list_empty_state',
            'sw_flow_list_empty_state_icon',
            'sw_flow_list_modal_content_custom',
        ],
        computed: [
            'flowRepository',
            'flowCriteria',
            'flowColumns',
            'detailPageLinkText',
            'assetFilter',
        ],
        methods: [
            'createComponent',
            'getList',
            'isValidTrigger',
            'onDuplicateFlow',
            'onEditFlow',
            'onDeleteFlow',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'updateRecords',
            'getTranslatedEventName',
            'selectionChange',
            'deleteWarningMessage',
            'bulkDeleteWarningMessage',
        ],
    },
});
