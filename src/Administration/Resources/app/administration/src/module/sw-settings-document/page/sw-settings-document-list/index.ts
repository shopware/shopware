import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-document-list',
    load: () => import('./src/sw-settings-document-list'),
    public: {
        blocks: [
            'sw_settings_list',
            'sw_settings_document_index',
            'sw_settings_document_list_search_bar',
            'sw_settings_document_list_smart_bar_header',
            'sw_settings_document_list_smart_bar_header_title',
            'sw_settings_document_list_smart_bar_header_title_text',
            'sw_settings_document_list_smart_bar_header_amount',
            'sw_settings_document_list_smart_bar_actions',
            'sw_settings_document_list_smart_bar_actions_add',
            'sw_settings_document_list_content',
            'sw_settings_document_list_content_card',
            'sw_settings_document_list_grid',
            'sw_product_list_grid_columns',
            'sw_settings_document_list_columns_name',
            'sw_settings_document_list_columns_name_link',
            'sw_settings_document_list_columns_name_link_inner',
            'sw_settings_document_list_columns_type',
            'sw_settings_document_list_columns_assignment',
            'sw_settings_document_list_columns_assignment_label',
            'sw_settings_document_list_columns_actions',
            'sw_settings_document_list_columns_actions_edit',
            'sw_settings_document_list_columns_actions_delete',
            'sw_settings_document_list_delete_modal',
            'sw_settings_document_list_delete_modal_confirm_text',
            'sw_settings_document_list_delete_modal_footer',
            'sw_settings_document_list_delete_modal_actions_cancel',
            'sw_settings_document_list_delete_modal_actions_delete',
            'sw_settings_document_list_grid_pagination',
        ],
        computed: [
            'filters',
            'listingCriteria',
            'expandButtonClass',
            'collapseButtonClass',
        ],
    },
});
