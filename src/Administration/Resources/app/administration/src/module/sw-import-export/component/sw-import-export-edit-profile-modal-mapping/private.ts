import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-import-export-edit-profile-modal-mapping',
    load: () => import('./src/sw-import-export-edit-profile-modal-mapping'),
    public: {
        props: [
            'profile',
            'systemRequiredFields',
        ],
        emits: [
            'update-mapping',
        ],
        blocks: [
            'sw_import_export_edit_profile_modal_mapping',
            'sw_import_export_edit_profile_modal_mapping_toolbar',
            'sw_import_export_edit_profile_modal_mapping_toolbar_search_field',
            'sw_import_export_edit_profile_modal_mapping_toolbar_add_mapping',
            'sw_import_export_edit_profile_modal_mapping_grid',
            'sw_import_export_edit_profile_modal_mapping_grid_csv_name_column',
            'sw_import_export_edit_profile_modal_mapping_grid_entry_column',
            'sw_import_export_edit_profile_modal_mapping_grid_required_column',
            'sw_import_export_edit_profile_modal_mapping_grid_default_value_column',
            'sw_import_export_edit_profile_modal_mapping_grid_position_column',
            'sw_import_export_edit_profile_modal_mapping_grid_position_column_button_group',
            'sw_import_export_edit_profile_modal_mapping_grid_position_column_button_group_up',
            'sw_import_export_edit_profile_modal_mapping_grid_position_column_button_group_up_icon',
            'sw_import_export_edit_profile_modal_mapping_grid_position_column_button_group_down',
            'sw_import_export_edit_profile_modal_mapping_grid_position_column_button_group_down_icon',
            'sw_import_export_edit_profile_modal_mapping_grid_context_menu_delete',
            'sw_import_export_edit_profile_modal_mapping_empty_state',
        ],
        computed: [
            'languageRepository',
            'currencyRepository',
            'customFieldSetRepository',
            'languageCriteria',
            'currencyCriteria',
            'customFieldSetCriteria',
            'mappingColumns',
            'mappingsExist',
            'sortedMappings',
        ],
        methods: [
            'createdComponent',
            'toggleAddMappingActionState',
            'onDeleteMapping',
            'loadMappings',
            'onAddMapping',
            'onSearch',
            'debouncedSearch',
            'isDefaultValueCheckboxDisabled',
            'isDefaultValueTextFieldDisabled',
            'isRequiredBySystem',
            'updateSorting',
            'swapItems',
            'isFirstMapping',
            'isLastMapping',
        ],
    },
});
