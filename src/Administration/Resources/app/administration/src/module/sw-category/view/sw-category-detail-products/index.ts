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
    name: 'sw-category-detail-products',
    load: () => import('./src/sw-category-detail-products'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_category_detail_products',
            'sw_category_detail_product_assignment',
            'sw_category_detail_product_assignment_type_select',
            'sw_category_detail_product_assignment_type_select_field',
            'sw_category_detail_product_assignment_manual_alert',
            'sw_category_detail_product_assignment_stream_select',
            'sw_category_detail_product_assignment_stream_select_field',
            'sw_category_detail_product_assignment_stream_preview_link',
            'sw_category_detail_product_assignment_stream_preview_grid',
            'sw_category_detail_product_assignment_column_name',
            'sw_category_detail_product_assignment_column_manufacturer',
            'sw_category_detail_product_assignment_empty_state',
        ],
        computed: [
            'category',
            'productStreamRepository',
            'productRepository',
            'productColumns',
            'manufacturerColumn',
            'nameColumn',
            'productCriteria',
            'productStreamCriteria',
            'productStreamInvalidError',
            'productAssignmentTypes',
            'dynamicProductGroupHelpText',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'loadProductStreamPreview',
            'onPaginateManualProductAssignment',
            'getParentProducts',
            'getItemName',
            'getManufacturer',
            'onUpdateProductAssignmentType',
        ],
    },
});
