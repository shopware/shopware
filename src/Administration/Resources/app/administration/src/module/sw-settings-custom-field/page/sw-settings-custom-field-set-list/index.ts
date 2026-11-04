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
    name: 'sw-settings-custom-field-set-list',
    load: () => import('./src/sw-settings-custom-field-set-list'),
    public: {
        blocks: [
            'sw_settings_custom_field_set_list',
            'sw_settings_custom_field_set_list_search_bar',
            'sw_settings_custom_field_set_list_header',
            'sw_settings_custom_field_set_list_actions',
            'sw_settings_custom_field_set_list_actions_add',
            'sw_settings_custom_field_set_list_content',
            'sw_settings_custom_field_set_list_content_inner',
            'sw_settings_custom_field_set_list_grid',
            'sw_settings_custom_field_set_list_grid_columns',
            'sw_settings_custom_field_set_list_grid_column_label',
            'sw_settings_custom_field_set_list_grid_column_actions',
            'sw_settings_custom_field_set_list_grid_delete_modal',
            'sw_settings_custom_field_set_list_grid_delete_modal_text',
            'sw_settings_custom_field_set_list_grid_delete_modal_footer',
            'sw_settings_custom_field_set_list_grid_delete_modal_cancel',
            'sw_settings_custom_field_set_list_grid_delete_modal_delete',
            'sw_settings_custom_field_set_list_content_columns_pagination',
            'sw_settings_custom_fields_set_list_empty_message',
        ],
        computed: [
            'titleSaveSuccess',
            'messageSaveSuccess',
            'listingCriteria',
        ],
        methods: [
            'getLocaleCriterias',
            'getTermCriteria',
            'onConfirmDelete',
        ],
    },
});
