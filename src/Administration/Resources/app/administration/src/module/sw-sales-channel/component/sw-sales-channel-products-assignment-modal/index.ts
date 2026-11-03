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
    name: 'sw-sales-channel-products-assignment-modal',
    load: () => import('./src/sw-sales-channel-products-assignment-modal'),
    public: {
        props: [
            'salesChannel',
            'isAssignProductLoading',
        ],
        emits: [
            'modal-close',
            'products-add',
        ],
        blocks: [
            'sw_sales_channel_products_assignment_modal',
            'sw_sales_channel_products_assignment_modal_tabs',
            'sw_sales_channel_products_assignment_modal_mt_tabs',
            'sw_sales_channel_products_assignment_modal_mt_tab_content',
            'sw_sales_channel_products_assignment_modal_mt_tab_content_single_products',
            'sw_sales_channel_products_assignment_modal_mt_tab_content_categories',
            'sw_sales_channel_products_assignment_modal_mt_tab_content_dynamic_product_groups',
            'sw_sales_channel_products_assignment_modal_tabs_single_products',
            'sw_sales_channel_products_assignment_modal_tabs_categories',
            'sw_sales_channel_products_assignment_modal_tab_dynamic_product_groups',
            'sw_sales_channel_products_assignment_modal_tab_content_single_products',
            'sw_sales_channel_products_assignment_modal_tab_content_categories',
            'sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups',
            'sw_sales_channel_products_assignment_modal_footer_cancel',
            'sw_sales_channel_products_assignment_modal_footer_submit',
        ],
        computed: [
            'productCount',
            'products',
            'localProducts',
            'selection',
            'productAssignmentTabs',
        ],
        methods: [
            'mountedComponent',
            'getProductContainerStyle',
            'getCategoryContainerStyle',
            'getProductGroupContainerStyle',
            'onChangeSelection',
            'onCloseModal',
            'onAddProducts',
            'setProductLoading',
        ],
    },
});
