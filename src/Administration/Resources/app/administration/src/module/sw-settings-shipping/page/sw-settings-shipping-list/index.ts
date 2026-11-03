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
    name: 'sw-settings-shipping-list',
    load: () => import('./src/sw-settings-shipping-list'),
    public: {
        blocks: [
            'sw_settings_shipping_index',
            'sw_settings_shipping_list_search_bar',
            'sw_settings_shipping_list_smart_bar_header',
            'sw_settings_shipping_list_smart_bar_header_title',
            'sw_settings_shipping_list_smart_bar_header_title_text',
            'sw_settings_shipping_list_smart_bar_header_amount',
            'sw_settings_shipping_list_smart_bar_actions',
            'sw_settings_shipping_list_smart_bar_actions_add',
            'sw_settings_shipping_list_language_switch',
            'sw_settings_shipping_list_content',
            'sw_settings_shipping_list_empty_state',
            'sw_settings_shipping_list_grid',
            'sw_setting_shipping_list_grid_columns',
            'sw_settings_shipping_list_grid_columns_active',
            'sw_settings_shipping_list_grid_columns_active_inline_edit',
            'sw_settings_shipping_list_grid_columns_active_content',
            'sw_settings_shipping_list_grid_columns_tax_type',
            'sw_settings_shipping_list_grid_columns_tax_type_inline_edit',
            'sw_settings_shipping_list_grid_columns_tax_type_content',
            'sw_settings_shipping_list_grid_columns_actions',
            'sw_settings_shipping_list_grid_columns_actions_edit',
            'sw_settings_shipping_list_grid_columns_actions_delete',
            'sw_settings_shipping_list_grid_action_modals',
            'sw_settings_shipping_list_delete_modal',
            'sw_settings_shipping_list_delete_modal_confirm_delete_text',
            'sw_settings_shipping_list_delete_modal_footer',
            'sw_settings_shipping_list_delete_modal_cancel',
            'sw_settings_shipping_list_delete_modal_confirm',
            'sw_settings_shipping_list_sidebar',
            'sw_settings_shipping_list_sidebar_refresh',
        ],
        computed: [
            'shippingRepository',
            'columns',
            'listingCriteria',
            'shippingCostTaxOptions',
        ],
        methods: [
            'getList',
            'onInlineEditSave',
            'onDelete',
            'onConfirmDelete',
            'onCloseDeleteModal',
            'onChangeLanguage',
            'shippingTaxTypeLabel',
        ],
    },
});
