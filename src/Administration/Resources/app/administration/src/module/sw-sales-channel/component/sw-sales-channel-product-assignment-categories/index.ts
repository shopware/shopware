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
    name: 'sw-sales-channel-product-assignment-categories',
    load: () => import('./src/sw-sales-channel-product-assignment-categories'),
    public: {
        props: [
            'salesChannel',
            'containerStyle',
        ],
        emits: [
            'selection-change',
            'product-loading',
        ],
        blocks: [
            'sw_sales_channel_product_assignment_categories',
            'sw_sales_channel_product_assignment_categories_info',
            'sw_sales_channel_product_assignment_categories_content',
            'sw_sales_channel_products_assignment_single_products_secondary',
            'sw_sales_channel_product_assignment_categories_search_field',
            'sw_sales_channel_product_assignment_categories_tree',
            'sw_sales_channel_product_assignment_categories_tree_result',
            'sw_sales_channel_product_assignment_categories_tree_headline',
            'sw_sales_channel_product_assignment_categories_tree_search',
            'sw_sales_channel_product_assignment_categories_tree_items',
            'ssw_sales_channel_product_assignment_categories_tree_item',
            'sw_sales_channel_product_assignment_categories_tree_item_actions',
            'sw_sales_channel_product_assignment_categories_search_results',
            'sw_sales_channel_product_assignment_categories_search_results_item',
            'sw_sales_channel_product_assignment_categories_search_results_item_checkbox',
            'sw_sales_channel_product_assignment_categories_search_results_item_folder',
            'sw_sales_channel_product_assignment_categories_search_results_item_folder_icon',
            'sw_category_tree_field_input_results_search_results_item_endpoint',
            'sw_category_tree_field_input_results_search_results_item_endpoint_icon',
            'sw_sales_channel_product_assignment_categories_search_results_item_name',
            'sw_category_tree_field_input_results_search_results_item_name_highlight',
            'sw_sales_channel_product_assignment_categories_search_results_empty',
        ],
        computed: [
            'categoryRepository',
            'productRepository',
            'selectedCategoriesItemsIds',
            'selectedCategoriesPathIds',
        ],
        methods: [
            'onSearchTermChange',
            'createdComponent',
            'categoryCriteria',
            'categorySearchCriteria',
            'getTreeItems',
            'onChangeSearchTerm',
            'onCheckItem',
            'removeItem',
            'searchCategories',
            'isSearchItemChecked',
            'onCheckSearchItem',
            'getBreadcrumb',
            'productCriteria',
            'getProductFromCategories',
        ],
    },
});
