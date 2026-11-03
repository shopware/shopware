import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-country-list',
    load: () => import('./src/sw-settings-country-list'),
    public: {
        blocks: [
            'sw_settings_list',
            'sw_settings_country_index',
            'sw_settings_country_list_search_bar',
            'sw_settings_country_list_smart_bar_header',
            'sw_settings_country_list_smart_bar_header_title',
            'sw_settings_country_list_smart_bar_header_title_text',
            'sw_settings_country_list_smart_bar_header_amount',
            'sw_settings_country_list_smart_bar_actions',
            'sw_settings_country_list_smart_bar_actions_add',
            'sw_settings_country_list_language_switch',
            'sw_settings_country_list_content',
            'sw_settings_country_list_content_card',
            'sw_settings_country_list_grid',
            'sw_settings_country_list_grid_inner',
            'sw_settings_country_list_columns_active_editor',
            'sw_settings_country_list_columns_active_label',
            'sw_settings_country_list_grid_columns_actions',
            'sw_settings_country_list_grid_columns_actions_edit',
            'sw_settings_country_list_grid_columns_actions_delete',
            'sw_settings_country_list_grid_action_modals',
            'sw_settings_country_list_delete_modal',
            'sw_settings_country_list_delete_modal_confirm_delete_text',
            'sw_settings_country_list_delete_modal_footer',
            'sw_settings_country_list_delete_modal_cancel',
            'sw_settings_country_list_delete_modal_confirm',
        ],
        computed: [
            'countryRepository',
            'detailPageLinkText',
        ],
        methods: [
            'getList',
            'onInlineEditSave',
            'onChangeLanguage',
            'onDelete',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'getCountryColumns',
        ],
    },
});
