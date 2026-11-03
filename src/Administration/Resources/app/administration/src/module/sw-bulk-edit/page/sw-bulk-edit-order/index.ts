import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-order',
    load: () => import('./src/sw-bulk-edit-order'),
    public: {
        blocks: [
            'sw_bulk_edit_order',
            'sw_bulk_edit_order_search_bar',
            'sw_bulk_edit_order_smart_bar_header',
            'sw_bulk_edit_order_content_smart_bar_actions',
            'sw_bulk_edit_order_actions_save',
            'sw_bulk_edit_order_content',
            'sw_bulk_edit_order_content_order_status_card',
            'sw_bulk_edit_order_content_order_status_section',
            'sw_bulk_edit_order_content_documents',
            'sw_bulk_edit_order_content_documents_content',
            'sw_bulk_edit_order_tags_card',
            'sw_bulk_edit_order_tags',
            'sw_bulk_edit_order_custom_field_card',
            'sw_bulk_edit_order_empty_state',
            'sw_bulk_edit_order_save_modal',
        ],
        computed: [
            'selectedIds',
            'stateMachineStateRepository',
            'orderRepository',
            'customFieldSetRepository',
            'customFieldSetCriteria',
            'hasChanges',
            'hasInvalidDocumentGenerationConfig',
            'restrictedFields',
            'statusFormFields',
            'documentsFormFields',
            'tagsFormFields',
        ],
        methods: [
            'createdComponent',
            'setRouteMetaModule',
            'loadBulkEditData',
            'fetchStatusOptions',
            'fetchStateMachineStates',
            'fetchToStateMachineTransitions',
            'toStateMachineStatesCriteria',
            'onProcessData',
            'openModal',
            'closeModal',
            'onSave',
            'getLatestOrderStatus',
            'loadCustomFieldSets',
            'onCustomFieldsChange',
            'onChangeDocument',
        ],
    },
});
