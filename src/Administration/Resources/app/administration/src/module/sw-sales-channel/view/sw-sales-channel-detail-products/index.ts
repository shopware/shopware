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
    name: 'sw-sales-channel-detail-products',
    load: () => import('./src/sw-sales-channel-detail-products'),
    public: {
        props: [
            'salesChannel',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_sales_channel_detail_products',
            'sw_sales_channel_detail_products_filled_state',
            'sw_sales_channel_detail_products_card_section',
            'sw_sales_channel_detail_products_card_section_secondary',
            'sw_sales_channel_detail_products_card_section_secondary_input',
            'sw_sales_channel_detail_products_card_section_secondary_button',
            'sw_sales_channel_detail_products_card_section_primary',
            'sw_sales_channel_detail_products_card_section_primary_filled_state',
            'sw_sales_channel_detail_products_listing_select_item_checkbox',
            'sw_sales_channel_detail_products_listing_column_name',
            'sw_sales_channel_detail_products_listing_column_active',
            'sw_sales_channel_detail_products_listing_pagination',
            'sw_sales_channel_detail_products_listing_delete_action',
            'sw_sales_channel_detail_products_listing_bulk',
            'sw_sales_channel_detail_products_card_section_primary_empty_state',
            'sw_sales_channel_detail_products_card_section_primary_image',
            'sw_sales_channel_detail_products_empty_state',
            'sw_sales_channel_detail_products_empty_state_button',
            'sw_sales_channel_detail_products_empty_state_image',
            'sw_sales_channel_detail_products_assignment_modal',
        ],
        computed: [
            'productRepository',
            'productVisibilityRepository',
            'productCriteria',
            'productColumns',
            'assetFilter',
        ],
        methods: [
            'getProducts',
            'onDeleteProduct',
            'onDeleteProducts',
            'getDeleteId',
            'showNotificationError',
            'onChangePage',
            'onChangeSearchTerm',
            'openAddProductsModal',
            'onAddProducts',
            'getExceededAssignmentLimit',
            'saveProductVisibilities',
            'isProductRemovable',
            'onProductSelectionChanged',
        ],
    },
});
