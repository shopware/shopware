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
    name: 'sw-order-create-details-header',
    load: () => import('./src/sw-order-create-details-header'),
    public: {
        props: [
            'customer',
            'orderDate',
            'cartPrice',
            'currency',
        ],
        emits: [
            'on-select-existing-customer',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_order_create_details_header',
            'sw_order_create_details_new_customer_modal',
            'sw_order_create_details_header_profile',
            'sw_order_create_details_header_profile_avatar',
            'sw_order_create_details_header_profile_searching',
            'sw_order_create_search_customer',
            'sw_order_create_search_customer_slot_selection_label_property',
            'sw_order_create_search_customer_slot_result_item',
            'sw_order_create_search_customer_slot_result_item_content_container',
            'sw_order_create_search_customer_slot_result_item_firstname',
            'sw_order_create_search_customer_slot_result_item_lastname',
            'sw_order_create_search_customer_slot_result_item_number',
            'sw_order_create_search_customer_slot_result_item_company',
            'sw_order_create_search_customer_slot_result_item_address',
            'sw_order_create_details_header_profile_summary',
        ],
        computed: [
            'customerId',
            'customerCriteria',
            'currencyFilter',
        ],
        methods: [
            'onSelectExistingCustomer',
            'onShowNewCustomerModal',
            'onCloseNewCustomerModal',
        ],
    },
});
