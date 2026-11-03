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
    name: 'sw-product-cross-selling-assignment',
    load: () => import('./src/sw-product-cross-selling-assignment'),
    public: {
        props: [
            'assignedProducts',
            'crossSellingId',
            'allowEdit',
        ],
        slots: [
            'result-item',
        ],
        blocks: [
            'sw_product_cross_selling_assignment_container',
            'sw_product_cross_selling_assignment_select',
            'sw_product_cross_selling_assignment_select_result_item',
            'sw_product_cross_selling_assignment_select_result_item_inner',
            'sw_entity_single_select_base_results_list_result_label',
            'sw_product_cross_selling_assignment_option_grid',
            'sw_data_grid_columns_render_value',
            'sw_product_cross_selling_assignment_option_grid_column_position',
            'sw_product_cross_selling_assignment_empty_state',
        ],
        computed: [
            'product',
            'isLoading',
            'isLoadingGrid',
            'assignmentRepository',
            'productRepository',
            'searchCriteria',
            'searchContext',
            'total',
            'assignedProductColumns',
            'variantProductIds',
            'variantCriteria',
            'assetFilter',
        ],
        methods: [
            'onToggleProduct',
            'removeItem',
            'isSelected',
        ],
    },
});
