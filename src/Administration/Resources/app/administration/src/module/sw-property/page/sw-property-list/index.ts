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
    name: 'sw-property-list',
    load: () => import('./src/sw-property-list'),
    public: {
        blocks: [
            'sw_property_list',
            'sw_property_list_search_bar',
            'sw_property_list_smart_bar_header',
            'sw_property_list_smart_bar_header_title',
            'sw_property_list_smart_bar_header_title_text',
            'sw_property_list_smart_bar_header_title_amount',
            'sw_property_list_',
            'sw_property_list_add_button',
            'sw_property_list_language_switch',
            'sw_property_list_content',
            'sw_property_list_empty_state',
            'sw_property_list_grid',
            'sw_property_list_grid_options',
            'sw_property_list_grid_filterable',
            'sw_property_list_grid_columns_filterable_inline_edit',
            'sw_property_list_grid_columns_filterable_content',
            'sw_property_list_grid_columns_actions',
            'sw_property_list_grid_columns_actions_detail',
            'sw_property_list_grid_columns_actions_delete',
            'sw_product_list_grid_action_modals',
            'sw_property_list_sidebar',
            'sw_property_list_sidebar_refresh_item',
        ],
        computed: [
            'propertyRepository',
            'defaultCriteria',
            'useNaturalSorting',
            'productRepository',
            'adminEsEnable',
        ],
        methods: [
            'onDelete',
            'onCloseDeleteModal',
            'usePropertyCriteria',
            'onConfirmDelete',
            'onChangeLanguage',
            'getList',
            'getPropertyColumns',
        ],
    },
});
