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
    name: 'sw-settings-salutation-list',
    load: () => import('./src/sw-settings-salutation-list'),
    public: {
        blocks: [
            'sw_settings_salutation_list',
            'sw_settings_salutation_list_search_bar',
            'sw_settings_salutation_list_smart_bar_header',
            'sw_settings_salutation_list_smart_bar_header_title',
            'sw_settings_salutation_list_smart_bar_header_title_text',
            'sw_settings_salutation_list_smart_bar_header_amount',
            'sw_settings_salutation_list_language_switch',
            'sw_settings_salutation_list_actions',
            'sw_settings_salutation_list_actions_create',
            'sw_settings_salutation_list_cardview',
            'sw_settings_salutation_list_card_content',
            'sw_settings_salutation_list_grid',
            'sw_settings_salutation_list_grid_columns_position',
            'sw_settings_salutation_list_grid_columns_position_inline_edit',
            'sw_settings_salutation_list_grid_columns_position_display',
        ],
        computed: [
            'columns',
            'salutationRepository',
            'tooltipAdd',
        ],
        methods: [
            'createdComponent',
            'getList',
            'getColumns',
        ],
    },
});
