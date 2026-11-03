import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-manufacturer-list',
    load: () => import('./src/sw-manufacturer-list'),
    public: {
        blocks: [
            'sw_manufacturer_list',
            'sw_manufacturer_list_search_bar',
            'sw_manufacturer_list_smart_bar_header',
            'sw_manufacturer_list_smart_bar_header_title',
            'sw_manufacturer_list_smart_bar_header_title_text',
            'sw_manufacturer_list_smart_bar_header_amount',
            'sw_manufacturer_list_actions',
            'sw_manufacturer_list_smart_bar_actions',
            'sw_manufacturer_list_language_switch',
            'sw_manufacturer_list_content',
            'sw_manufacturer_list_empty_state',
            'sw_manufacturer_list_grid',
            'sw_manufacturer_list_grid_columns_name_preview',
        ],
        computed: [
            'manufacturerRepository',
            'manufacturerColumns',
            'manufacturerCriteria',
            'adminEsEnable',
        ],
        methods: [
            'onChangeLanguage',
            'getList',
            'updateTotal',
        ],
    },
});
