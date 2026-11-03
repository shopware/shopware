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
    name: 'sw-settings-customer-group-list',
    load: () => import('./src/sw-settings-customer-group-list'),
    public: {
        blocks: [
            'sw_settings_customer_group_list',
            'sw_settings_customer_group_list_search_bar',
            'sw_settings_customer_group_list_smart_bar_header',
            'sw_settings_customer_group_list_smart_bar_header_title',
            'sw_settings_customer_group_list_smart_bar_header_title_text',
            'sw_settings_customer_group_list_smart_bar_header_amount',
            'sw_settings_customer_group_list_language_switch',
            'sw_settings_customer_group_list_actions',
            'sw_settings_customer_group_list_actions_create',
            'sw_settings_customer_group_list_cardview',
            'sw_settings_customer_group_list_card_content',
            'sw_settings_customer_group_list_grid',
            'sw_settings_customer_group_list_grid_column_display_gross',
            'sw_settings_customer_group_list_grid_column_display_gross_inner',
            'sw_settings_customer_group_list_empty_state',
        ],
        computed: [
            'columns',
            'customerGroupRepository',
            'showEmptyState',
            'allCustomerGroupsCriteria',
        ],
        methods: [
            'createdComponent',
            'getList',
            'getColumns',
            'customerGroupCriteriaWithFilter',
            'createErrorNotification',
            'customerGroupCanBeDeleted',
            'deleteCustomerGroup',
            'deleteCustomerGroups',
            'onContextMenuDelete',
        ],
    },
});
