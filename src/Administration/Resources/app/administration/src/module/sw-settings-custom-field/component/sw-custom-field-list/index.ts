import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-custom-field-list',
    load: () => import('./src/sw-custom-field-list'),
    public: {
        props: [
            'set',
        ],
        emits: [
            'loading-changed',
        ],
        blocks: [
            'sw_custom_field_list',
            'sw_custom_field_list_toolbar',
            'sw_custom_field_list_toolbar_searchfield',
            'sw_custom_field_list_toolbar_delete',
            'sw_custom_field_list_toolbar_add',
            'sw_custom_field_list_grid',
            'sw_custom_field_list_grid_column_label',
            'sw_custom_field_list_grid_column_type',
            'sw_custom_field_list_grid_column_custom_field_position',
            'sw_custom_field_list_grid_column_custom_field_position_edit',
            'sw_custom_field_list_grid_column_actions',
            'sw_custom_field_list_grid_column_actions_edit',
            'sw_custom_field_list_grid_column_actions_reset_delete',
            'sw_custom_field_list_grid_column_actions_delete',
            'sw_custom_field_list_grid_pagination',
            'sw_custom_field_list_empty_state',
            'sw_custom_field_list_custom_field_detail',
            'sw_custom_field_list_custom_field_delete',
            'sw_custom_field_list_custom_field_delete_text',
            'sw_custom_field_list_custom_field_delete_actions',
            'sw_custom_field_list_custom_field_delete_action_cancel',
            'sw_custom_field_list_custom_field_delete_action_confirm',
            'sw_custom_field_list_loader',
        ],
        computed: [
            'customFieldRepository',
            'globalCustomFieldRepository',
        ],
        methods: [
            'onSearchTermChange',
            'createdComponent',
            'loadCustomFields',
            'selectionChanged',
            'onCustomFieldDelete',
            'onDeleteCustomFields',
            'onAddCustomField',
            'onCancelCustomField',
            'onInlineEditFinish',
            'onSaveCustomField',
            'onInlineEditCancel',
            'onCustomFieldEdit',
            'removeEmptyProperties',
            'isCustomFieldNameUnique',
            'onPageChange',
            'onCancelDeleteCustomField',
            'onDeleteCustomField',
        ],
    },
});
