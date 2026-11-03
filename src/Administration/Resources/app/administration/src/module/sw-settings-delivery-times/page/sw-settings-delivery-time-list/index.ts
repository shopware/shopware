import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-delivery-time-list',
    load: () => import('./src/sw-settings-delivery-time-list'),
    public: {
        blocks: [
            'sw_settings_delivery_time_list',
            'sw_settings_delivery_time_list_smart_bar_header',
            'sw_settings_delivery_time_list_smart_bar_header_title',
            'sw_settings_delivery_time_list_smart_bar_header_title_text',
            'sw_settings_delivery_time_list_search_bar',
            'sw_settings_delivery_time_list_language_switch',
            'sw_settings_delivery_time_list_smart_bar_actions',
            'sw_settings_delivery_time_list_smart_bar_actions_create',
            'sw_settings_delivery_time_list_content',
            'sw_settings_delivery_time_list_content_inner',
            'sw_settings_delivery_time_list_grid_wrapper',
            'sw_settings_delivery_time_list_grid',
            'sw_settings_delivery_time_list_grid_column_unit',
        ],
        computed: [
            'deliveryTimeRepository',
        ],
        methods: [
            'getList',
            'onChangeLanguage',
            'deliveryTimeColumns',
        ],
    },
});
