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
    name: 'sw-order-customer-grid',
    load: () => import('./src/sw-order-customer-grid'),
    public: {
        blocks: [
            'sw_order_customer_grid',
            'sw_order_customer_grid_toolbar',
            'sw_order_customer_grid_search_input',
            'sw_order_customer_grid_add_new_customer',
            'sw_order_customer_grid_content',
            'sw_order_customer_grid_content_column_select',
            'sw_order_customer_grid_content_grid_column_name',
            'sw_settings_product_feature_set_list_grid_columns_actions',
            'sw_order_customer_grid_empty_state',
            'sw_order_customer_grid_new_customer_modal',
            'sw_order_customer_grid_sales_channel_select_modal',
            'sw_order_customer_grid_sales_channel_description',
            'sw_order_customer_grid_sales_channel_select',
            'sw_order_customer_grid_sales_channel_notification_alert',
            'sw_order_customer_grid_sales_channel_action_close',
            'sw_order_customer_grid_sales_channel_action_select',
            'sw_order_customer_grid_customer_changes_modal',
            'sw_order_customer_grid_customer_changes_modal_description',
            'sw_order_customer_grid_customer_changes_modal_action_close',
            'sw_order_customer_grid_customer_changes_modal_action_changes',
            'sw_order_customer_grid_empty_state_icon',
        ],
        computed: [
            'customerData',
            'customerRepository',
            'customerCriteria',
            'customerCriterion',
            'customerColumns',
            'showEmptyState',
            'emptyTitle',
            'cart',
            'assetFilter',
            'salesChannelRepository',
            'salesChannelCriteria',
            'isSelectSalesChannelDisabled',
        ],
        methods: [
            'mountedComponent',
            'getList',
            'onShowNewCustomerModal',
            'isChecked',
            'onCheckCustomer',
            'createCart',
            'setCustomer',
            'handleSelectCustomer',
            'onAddNewCustomer',
            'updateCustomerContext',
            'getCart',
            'loadSalesChannel',
            'onSalesChannelChange',
            'onCloseSalesChannelSelectModal',
            'onSelectSalesChannel',
            'customerUnavailable',
            'onChangeCustomer',
            'onCloseCustomerChangesModal',
            'checkContextLanguage',
        ],
    },
});
